<?php
// Recurring Obligations — list and search.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ObligationManagement.php';
Application::init();
require_login();

$search = trim($_GET['q'] ?? '');
$obligations = ObligationManagement::listObligations($search);

// One-shot flash from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$today = date('Y-m-d');

header_html('Recurring Obligations');
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Recurring Obligations</h2>
  <a class="button primary" href="/obligations/add.php">Add Obligation</a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="get" data-auto-submit>
    <label>Search
      <input type="text" name="q" value="<?=h($search)?>" placeholder="Title, category, or description">
    </label>
  </form>
</div>

<?php if (empty($obligations)): ?>
  <p class="small"><?= $search !== '' ? 'No obligations match your search.' : 'No obligations yet. Add recurring responsibilities like property taxes, passport renewals, or HVAC filter changes.' ?></p>
<?php else: ?>
  <div class="card">
    <table class="list">
      <thead>
        <tr>
          <th>Title</th>
          <th>Category</th>
          <th>Repeats</th>
          <th>Responsible</th>
          <th>Next due</th>
          <th>Last completed</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($obligations as $o): ?>
          <tr<?= empty($o['is_active']) ? ' style="opacity:0.55;"' : '' ?>>
            <td>
              <a href="/obligations/edit.php?id=<?= (int)$o['id'] ?>"><?=h($o['title'])?></a>
              <?php if (empty($o['is_active'])): ?><span class="badge">Inactive</span><?php endif; ?>
              <?php if (!empty($o['applies_to_name'])): ?>
                <div class="small">For <?=h($o['applies_to_name'])?></div>
              <?php endif; ?>
            </td>
            <td><?=h($o['category'] ?? '')?></td>
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
  </div>
<?php endif; ?>

<?php footer_html(); ?>
