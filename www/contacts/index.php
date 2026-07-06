<?php
// Contacts / Directory — list with group filters and search.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ContactManagement.php';
Application::init();
require_login();

$search = trim($_GET['q'] ?? '');
$group = trim($_GET['group'] ?? '');
$category = trim($_GET['category'] ?? '');

// Resolve the active filter to a set of categories
$filterCategories = [];
if ($category !== '' && in_array($category, ContactManagement::CATEGORIES, true)) {
    $filterCategories = [$category];
    $group = '';
} elseif ($group !== '' && isset(ContactManagement::CATEGORY_GROUPS[$group])) {
    $filterCategories = ContactManagement::CATEGORY_GROUPS[$group];
} else {
    $group = '';
    $category = '';
}

$contacts = ContactManagement::listContacts($search, $filterCategories);

// One-shot flash from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

function filter_url(string $search, string $group = '', string $category = ''): string {
    $params = [];
    if ($search !== '') $params['q'] = $search;
    if ($group !== '') $params['group'] = $group;
    if ($category !== '') $params['category'] = $category;
    return '/contacts/' . (!empty($params) ? '?' . http_build_query($params) : '');
}

header_html('Contacts');
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Contacts</h2>
  <a class="button primary" href="/contacts/add.php">Add Contact</a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <div class="filter-chips" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
    <a class="button small <?= $group === '' && $category === '' ? 'primary' : '' ?>" href="<?=h(filter_url($search))?>">All</a>
    <?php foreach (array_keys(ContactManagement::CATEGORY_GROUPS) as $g): ?>
      <a class="button small <?= $group === $g ? 'primary' : '' ?>" href="<?=h(filter_url($search, $g))?>"><?=h($g)?></a>
    <?php endforeach; ?>
  </div>
  <form method="get" data-auto-submit>
    <?php if ($group !== ''): ?><input type="hidden" name="group" value="<?=h($group)?>"><?php endif; ?>
    <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?=h($category)?>"><?php endif; ?>
    <label>Search
      <input type="text" name="q" value="<?=h($search)?>" placeholder="Name, organization, email, phone, or notes">
    </label>
  </form>
</div>

<?php if (empty($contacts)): ?>
  <p class="small"><?= ($search !== '' || !empty($filterCategories)) ? 'No contacts match.' : 'No contacts yet. Add doctors, contractors, advisors, schools, and other people your family relies on.' ?></p>
<?php else: ?>
  <div class="card">
    <table class="list">
      <thead>
        <tr>
          <th>Name</th>
          <th>Categories</th>
          <th>Phone</th>
          <th>Email</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contacts as $contact): ?>
          <tr>
            <td>
              <a href="/contacts/edit.php?id=<?= (int)$contact['id'] ?>"><?=h($contact['name'])?></a>
              <?php if (!empty($contact['organization'])): ?>
                <div class="small"><?=h($contact['organization'])?><?= !empty($contact['job_title']) ? ' — ' . h($contact['job_title']) : '' ?></div>
              <?php elseif ($contact['contact_type'] === 'organization'): ?>
                <div class="small">Organization</div>
              <?php endif; ?>
            </td>
            <td>
              <?php foreach (array_filter(array_map('trim', explode(',', (string)($contact['categories'] ?? '')))) as $c): ?>
                <a class="badge" style="background:#eef2ff;color:#3730a3;text-decoration:none;" href="<?=h(filter_url('', '', $c))?>"><?=h($c)?></a>
              <?php endforeach; ?>
            </td>
            <td><?= !empty($contact['phone']) ? '<a href="tel:' . h($contact['phone']) . '">' . h($contact['phone']) . '</a>' : '' ?></td>
            <td><?= !empty($contact['email']) ? '<a href="mailto:' . h($contact['email']) . '">' . h($contact['email']) . '</a>' : '' ?></td>
            <td class="small" style="text-align:right;">
              <a class="button small" href="/contacts/edit.php?id=<?= (int)$contact['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php footer_html(); ?>
