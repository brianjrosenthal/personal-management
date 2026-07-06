<?php
// Homepage — the recurring obligations dashboard: what needs attention now.
require_once __DIR__ . '/partials.php';
require_once __DIR__ . '/lib/ObligationManagement.php';
Application::init();
require_login();

$me = current_user();
$announcement = Settings::announcement();
$today = date('Y-m-d');
$groups = ObligationManagement::dashboardObligations($today);

// One-shot flash from complete_eval.php
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$nothingDue = empty($groups['overdue']) && empty($groups['due_today']) && empty($groups['upcoming']);

// One dashboard section (Overdue / Due Today / Upcoming)
function dashboard_section(string $title, array $rows, string $today): void {
    if (empty($rows)) return;
?>
  <div class="card">
    <h3><?=h($title)?></h3>
    <table class="list">
      <tbody>
        <?php foreach ($rows as $o): ?>
          <tr>
            <td>
              <a href="/obligations/edit.php?id=<?= (int)$o['id'] ?>"><?=h($o['title'])?></a>
              <?php if (!empty($o['applies_to_name'])): ?>
                <span class="small">for <?=h($o['applies_to_name'])?></span>
              <?php endif; ?>
              <?php if (!empty($o['responsible_name'])): ?>
                <div class="small"><?=h($o['responsible_name'])?></div>
              <?php endif; ?>
            </td>
            <td><?= obligation_due_html($o['next_due_on'], $today) ?></td>
            <td style="text-align:right;white-space:nowrap;">
              <form method="post" action="/obligations/complete_eval.php" style="display:inline;">
                <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                <input type="hidden" name="obligation_id" value="<?= (int)$o['id'] ?>">
                <input type="hidden" name="completed_on" value="<?=h($today)?>">
                <input type="hidden" name="return" value="/index.php">
                <button class="button small" type="submit">Mark Complete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php
}

header_html('Home');
?>

<?php if (trim($announcement) !== ''): ?>
  <p class="announcement"><?=h($announcement)?></p>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Hello, <?=h($me['first_name'] ?? '')?></h2>
  <div style="display:flex;gap:8px;">
    <a class="button" href="/obligations/">All Obligations</a>
    <a class="button primary" href="/obligations/add.php">Add Obligation</a>
  </div>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<?php if ($nothingDue): ?>
  <div class="card">
    <h3>All caught up 🎉</h3>
    <p class="small">Nothing is overdue or coming due in the next 30 days.</p>
  </div>
<?php else: ?>
  <?php dashboard_section('Overdue', $groups['overdue'], $today); ?>
  <?php dashboard_section('Due Today', $groups['due_today'], $today); ?>
  <?php dashboard_section('Upcoming (next 30 days)', $groups['upcoming'], $today); ?>
<?php endif; ?>

<?php footer_html(); ?>
