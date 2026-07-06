<?php
// Recurring Obligations — list, with three views: flat list (by next due),
// grouped by month, and grouped by category.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ObligationManagement.php';
Application::init();
require_login();

$search = trim($_GET['q'] ?? '');
$view = $_GET['view'] ?? 'list';
if (!in_array($view, ['list', 'month', 'category'], true)) {
    $view = 'list';
}

$obligations = ObligationManagement::listObligations($search);

// One-shot flash from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$today = date('Y-m-d');

function obligations_view_url(string $view, string $search): string {
    $params = [];
    if ($view !== 'list') $params['view'] = $view;
    if ($search !== '') $params['q'] = $search;
    return '/obligations/' . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Table header + rows shared by all three views. $showCategory lets the
// category view drop its redundant column.
function obligations_table(array $rows, string $today, bool $showCategory = true): void {
?>
    <table class="list">
      <thead>
        <tr>
          <th>Title</th>
          <?php if ($showCategory): ?><th>Category</th><?php endif; ?>
          <th>Repeats</th>
          <th>Responsible</th>
          <th>Next due</th>
          <th>Last completed</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $o): ?>
          <tr<?= empty($o['is_active']) ? ' style="opacity:0.55;"' : '' ?>>
            <td>
              <a href="/obligations/edit.php?id=<?= (int)$o['id'] ?>"><?=h($o['title'])?></a>
              <?php if (empty($o['is_active'])): ?><span class="badge">Inactive</span><?php endif; ?>
              <?php if (!empty($o['applies_to_name'])): ?>
                <div class="small">For <?=h($o['applies_to_name'])?></div>
              <?php endif; ?>
            </td>
            <?php if ($showCategory): ?><td><?=h($o['category'] ?? '')?></td><?php endif; ?>
            <td class="small"><?=h(ObligationManagement::describeRecurrence($o))?></td>
            <td><?=h($o['responsible_name'] ?? '')?></td>
            <td>
              <?php if ($o['recurrence_type'] === 'does_not_repeat' && !$o['next_due_on'] && $o['last_completed_on']): ?>
                <span class="status-verified">Completed</span>
              <?php elseif (!empty($o['is_active'])): ?>
                <?= obligation_due_html($o['next_due_on'], $today) ?>
              <?php else: ?>
                <span class="small">—</span>
              <?php endif; ?>
            </td>
            <td><?= $o['last_completed_on'] ? h(date('M j, Y', strtotime($o['last_completed_on']))) : '<span class="small">Never</span>' ?></td>
            <td class="small" style="text-align:right;">
              <a class="button small" href="/obligations/edit.php?id=<?= (int)$o['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
<?php
}

// Group rows for the month view: active obligations by due month (chronological),
// then "Not scheduled" (active, no next due), then "Inactive".
function group_obligations_by_month(array $obligations): array {
    $groups = [];
    $notScheduled = [];
    $inactive = [];
    foreach ($obligations as $o) {
        if (empty($o['is_active'])) {
            $inactive[] = $o;
        } elseif (empty($o['next_due_on'])) {
            $notScheduled[] = $o;
        } else {
            $month = substr($o['next_due_on'], 0, 7); // YYYY-MM, already sorted by next_due_on
            $groups[$month][] = $o;
        }
    }

    $out = [];
    foreach ($groups as $month => $rows) {
        $out[date('F Y', strtotime($month . '-01'))] = $rows;
    }
    if (!empty($notScheduled)) $out['Not scheduled'] = $notScheduled;
    if (!empty($inactive)) $out['Inactive'] = $inactive;
    return $out;
}

// Group rows for the category view: alphabetical categories, "Uncategorized" last.
function group_obligations_by_category(array $obligations): array {
    $groups = [];
    $uncategorized = [];
    foreach ($obligations as $o) {
        $category = trim((string)($o['category'] ?? ''));
        if ($category === '') {
            $uncategorized[] = $o;
        } else {
            $groups[$category][] = $o;
        }
    }
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
    if (!empty($uncategorized)) $groups['Uncategorized'] = $uncategorized;
    return $groups;
}

header_html('Recurring Obligations');
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Recurring Obligations</h2>
  <a class="button primary" href="/obligations/add.php">Add Obligation</a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
    <a class="button small <?= $view === 'list' ? 'primary' : '' ?>" href="<?=h(obligations_view_url('list', $search))?>">List</a>
    <a class="button small <?= $view === 'month' ? 'primary' : '' ?>" href="<?=h(obligations_view_url('month', $search))?>">By Month</a>
    <a class="button small <?= $view === 'category' ? 'primary' : '' ?>" href="<?=h(obligations_view_url('category', $search))?>">By Category</a>
  </div>
  <form method="get" data-auto-submit>
    <?php if ($view !== 'list'): ?><input type="hidden" name="view" value="<?=h($view)?>"><?php endif; ?>
    <label>Search
      <input type="text" name="q" value="<?=h($search)?>" placeholder="Title, category, or description">
    </label>
  </form>
</div>

<?php if (empty($obligations)): ?>
  <p class="small"><?= $search !== '' ? 'No obligations match your search.' : 'No obligations yet. Add recurring responsibilities like property taxes, passport renewals, or HVAC filter changes.' ?></p>
<?php elseif ($view === 'month'): ?>
  <?php foreach (group_obligations_by_month($obligations) as $label => $rows): ?>
    <div class="card">
      <h3><?=h($label)?> <span class="small">(<?= count($rows) ?>)</span></h3>
      <?php obligations_table($rows, $today); ?>
    </div>
  <?php endforeach; ?>
<?php elseif ($view === 'category'): ?>
  <?php foreach (group_obligations_by_category($obligations) as $label => $rows): ?>
    <div class="card">
      <h3><?=h($label)?> <span class="small">(<?= count($rows) ?>)</span></h3>
      <?php obligations_table($rows, $today, false); ?>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="card">
    <?php obligations_table($obligations, $today); ?>
  </div>
<?php endif; ?>

<?php footer_html(); ?>
