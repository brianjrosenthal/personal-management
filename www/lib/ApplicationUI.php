<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/Files.php';

class ApplicationUI {

    /**
     * Generate a cache-busted URL for a static resource
     */
    public static function staticResourceUrl(string $path): string {
        $filePath = __DIR__ . '/../' . ltrim($path, '/');
        $version = @filemtime($filePath);
        if (!$version) {
            $version = date('Ymd');
        }
        return $path . '?v=' . $version;
    }

    /**
     * Generate a complete CSS link tag with cache-busting
     */
    public static function cssLink(string $path): string {
        $url = self::staticResourceUrl($path);
        return '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Generate a complete JS script tag with cache-busting
     */
    public static function jsScript(string $path): string {
        $url = self::staticResourceUrl($path);
        return '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    /**
     * Main menu entries shown in the left sidebar. As modules are built
     * (obligations, assets, documents, contacts, insurance) they get added here.
     */
    private static function mainMenuItems(): array {
        return [
            ['path' => '/index.php', 'label' => 'Home'],
            ['path' => '/upcoming_tasks.php', 'label' => 'Upcoming Tasks'],
        ];
    }

    /**
     * Page shell with left sidebar navigation:
     * - main menu at the top of the sidebar (scrolls if too long)
     * - Admin menu and profile photo anchored at the bottom
     * - on narrow screens the sidebar collapses behind a top-left menu icon
     */
    public static function headerHtml(string $title): void {
        $u = current_user();
        $cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $siteTitle = Settings::siteTitle();

        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>'.h($title).' - '.h($siteTitle).'</title>';
        echo self::cssLink('/styles.css');
        echo '</head><body>';

        if ($u) {
            // Mobile top bar: menu icon + site title
            echo '<div class="mobile-topbar">'
               . '<button id="sidebarToggle" class="sidebar-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar">'
               . '<span></span><span></span><span></span>'
               . '</button>'
               . '<a class="mobile-topbar-title" href="/index.php">'.h($siteTitle).'</a>'
               . '</div>';
            echo '<div id="sidebarBackdrop" class="sidebar-backdrop"></div>';

            echo '<aside class="sidebar" id="sidebar">';
            echo '<div class="sidebar-title"><a href="/index.php">'.h($siteTitle).'</a></div>';

            // Main menu (scrollable middle section)
            echo '<nav class="sidebar-nav">';
            foreach (self::mainMenuItems() as $item) {
                $active = ($cur === basename($item['path']));
                echo '<a href="'.h($item['path']).'" class="sidebar-item'.($active ? ' active' : '').'">'.h($item['label']).'</a>';
            }
            echo '</nav>';

            // Bottom-anchored: Admin menu (peer of the profile photo) + profile
            echo '<div class="sidebar-bottom">';

            if (!empty($u['is_admin'])) {
                echo '<div class="sidebar-menu-wrap">'
                   . '<button type="button" id="adminToggle" class="sidebar-item sidebar-menu-toggle" aria-expanded="false" aria-controls="adminMenu">'
                   . '<span class="sidebar-item-icon" aria-hidden="true">&#9881;</span> Admin'
                   . '</button>'
                   . '<div id="adminMenu" class="popup-menu hidden" role="menu" aria-hidden="true">'
                   .   '<a href="/admin/users.php" role="menuitem">Users</a>'
                   .   '<a href="/admin/settings.php" role="menuitem">Settings</a>'
                   .   '<a href="/admin/activity_log.php" role="menuitem">Activity Log</a>'
                   .   '<a href="/admin/email_log.php" role="menuitem">Email Log</a>'
                   . '</div>'
                   . '</div>';
            }

            $name = trim((string)($u['first_name'] ?? '').' '.(string)($u['last_name'] ?? ''));
            $initials = strtoupper((string)substr((string)($u['first_name'] ?? ''), 0, 1).(string)substr((string)($u['last_name'] ?? ''), 0, 1));
            $photoUrl = Files::profilePhotoUrl($u['photo_public_file_id'] ?? null, 32);

            if ($photoUrl !== '') {
                $avatar = '<img class="sidebar-avatar" src="'.h($photoUrl).'" alt="">';
            } else {
                $avatar = '<span class="sidebar-avatar sidebar-avatar-initials" aria-hidden="true">'.h($initials).'</span>';
            }

            echo '<div class="sidebar-menu-wrap">'
               . '<button type="button" id="profileToggle" class="sidebar-item sidebar-menu-toggle sidebar-profile" aria-expanded="false" aria-controls="profileMenu" title="Account">'
               . $avatar
               . '<span class="sidebar-profile-name">'.h($name).'</span>'
               . '</button>'
               . '<div id="profileMenu" class="popup-menu hidden" role="menu" aria-hidden="true">'
               .   '<a href="/profile/" role="menuitem">My Profile</a>'
               .   '<a href="/profile/change_password.php" role="menuitem">Change Password</a>'
               .   '<a href="/logout.php" role="menuitem">Logout</a>'
               . '</div>'
               . '</div>';

            echo '</div>'; // .sidebar-bottom
            echo '</aside>';

            // Sidebar behavior: mobile drawer + bottom popup menus
            echo '<script>document.addEventListener("DOMContentLoaded",function(){'
               . 'var sidebar=document.getElementById("sidebar");'
               . 'var toggle=document.getElementById("sidebarToggle");'
               . 'var backdrop=document.getElementById("sidebarBackdrop");'
               . 'function closeSidebar(){sidebar.classList.remove("open");backdrop.classList.remove("show");if(toggle)toggle.setAttribute("aria-expanded","false");}'
               . 'function openSidebar(){sidebar.classList.add("open");backdrop.classList.add("show");if(toggle)toggle.setAttribute("aria-expanded","true");}'
               . 'if(toggle){toggle.addEventListener("click",function(){sidebar.classList.contains("open")?closeSidebar():openSidebar();});}'
               . 'if(backdrop){backdrop.addEventListener("click",closeSidebar);}'
               . 'var wraps=Array.prototype.slice.call(document.querySelectorAll(".sidebar .sidebar-menu-wrap"));'
               . 'function closeMenus(){wraps.forEach(function(w){var m=w.querySelector(".popup-menu");var b=w.querySelector(".sidebar-menu-toggle");if(m){m.classList.add("hidden");m.setAttribute("aria-hidden","true");}if(b){b.setAttribute("aria-expanded","false");}});}'
               . 'wraps.forEach(function(w){var m=w.querySelector(".popup-menu");var b=w.querySelector(".sidebar-menu-toggle");if(!m||!b)return;b.addEventListener("click",function(e){e.preventDefault();var isHidden=m.classList.contains("hidden");closeMenus();if(isHidden){m.classList.remove("hidden");m.setAttribute("aria-hidden","false");b.setAttribute("aria-expanded","true");}});});'
               . 'document.addEventListener("click",function(e){var inWrap=wraps.some(function(w){return w.contains(e.target);});if(!inWrap)closeMenus();});'
               . 'document.addEventListener("keydown",function(e){if(e.key==="Escape"){closeMenus();closeSidebar();}});'
               . '});</script>';

            echo '<div class="content"><main>';
        } else {
            // Logged-out shell (rarely used; auth pages render their own layout)
            echo '<div class="mobile-topbar always"><a class="mobile-topbar-title" href="/login.php">'.h($siteTitle).'</a></div>';
            echo '<div class="content no-sidebar"><main>';
        }
    }

    public static function footerHtml(): void {
        echo '</main></div>' . self::jsScript('/main.js') . '</body></html>';
    }
}
