<?php
// Edit a recurring obligation — form, mark-complete, and completion history.
// Evaluates to obligations/edit_eval.php / complete_eval.php / completion_remove_eval.php.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ObligationManagement.php';
require_once __DIR__ . '/../lib/UserManagement.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
require_once __DIR__ . '/../lib/DocumentManagement.php';
require_once __DIR__ . '/../lib/InsurancePolicyManagement.php';
require_once __DIR__ . '/../lib/ContactManagement.php';
require_once __DIR__ . '/form_fields.php';
Application::init();
require_login();

$obligationId = (int)($_GET['id'] ?? 0);
$obligation = $obligationId > 0 ? ObligationManagement::getObligation($obligationId) : null;
if (!$obligation) {
    $_SESSION['error'] = 'Obligation not found.';
    header('Location: /obligations/');
    exit;
}

$completions = ObligationManagement::listCompletions($obligationId);
$linked = ObligationManagement::getLinkedObjects($obligationId);

// One-shot flash + form repopulation from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['form_data']);

if (!empty($form)) {
    $values = $form + [
        'linked_asset_ids' => (array)($form['link_assets'] ?? []),
        'linked_document_ids' => (array)($form['link_documents'] ?? []),
        'linked_policy_ids' => (array)($form['link_policies'] ?? []),
        'linked_contact_ids' => (array)($form['link_contacts'] ?? []),
    ];
    if (empty($form['is_active'])) $values['is_active'] = 0;
} else {
    $values = $obligation;
    if (!empty($obligation['annual_month_day'])) {
        [$values['annual_month'], $values['annual_day']] = array_map('intval', explode('-', $obligation['annual_month_day']));
    }
    if ($obligation['recurrence_type'] === 'after_completion') {
        $values['anchor_date_after'] = $obligation['anchor_date'] ?? '';
    }
    if ($obligation['recurrence_type'] === 'does_not_repeat') {
        $values['anchor_date_once'] = $obligation['anchor_date'] ?? '';
    }
}

$opts = [
    'users' => UserManagement::listUsers(),
    'assets' => AssetManagement::listAssets(),
    'documents' => DocumentManagement::listDocuments(),
    'policies' => InsurancePolicyManagement::listPolicies(),
    'contacts' => ContactManagement::listContacts(),
];

$today = date('Y-m-d');

header_html('Edit ' . $obligation['title']);
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Edit <?=h($obligation['title'])?></h2>
  <a class="button" href="/obligations/">Back to Obligations</a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
    <div><strong>Next due:</strong>
      <?php if ($obligation['recurrence_type'] === 'does_not_repeat' && !$obligation['next_due_on'] && $obligation['last_completed_on']): ?>
        <span class="status-verified">Completed</span>
      <?php else: ?>
        <?= obligation_due_html($obligation['next_due_on'], $today) ?>
      <?php endif; ?>
    </div>
    <div><strong>Last completed:</strong> <?= $obligation['last_completed_on'] ? h(date('M j, Y', strtotime($obligation['last_completed_on']))) : 'Never' ?></div>
    <div><strong>Repeats:</strong> <?=h(ObligationManagement::describeRecurrence($obligation))?></div>
  </div>
</div>

<div class="card">
  <h3>Mark Complete</h3>
  <form method="post" action="/obligations/complete_eval.php" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="obligation_id" value="<?= (int)$obligationId ?>">
    <input type="hidden" name="return" value="/obligations/edit.php?id=<?= (int)$obligationId ?>">
    <div class="grid" style="grid-template-columns:220px 1fr auto;gap:12px;align-items:end;">
      <label>Completed on
        <input type="date" name="completed_on" value="<?=h($today)?>" required>
      </label>
      <label>Notes (optional)
        <input type="text" name="notes" placeholder="e.g. Paid online, confirmation #12345">
      </label>
      <button class="primary" type="submit">Mark Complete</button>
    </div>
  </form>
</div>

<div class="card">
  <form method="post" action="/obligations/edit_eval.php" class="stack" data-warn-unsaved>
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$obligationId ?>">
    <?php render_obligation_form_fields($values, $opts); ?>
    <div class="actions">
      <button class="primary" type="submit">Save Obligation</button>
      <a class="button" href="/obligations/">Cancel</a>
    </div>
  </form>
</div>

<?php if (array_filter($linked)): ?>
<div class="card">
  <h3>Linked Records</h3>
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
    <?php
      $linkViews = [
        'assets' => ['Household assets', '/assets/edit.php?id='],
        'documents' => ['Documents', '/documents/edit.php?id='],
        'policies' => ['Insurance policies', '/insurance/edit.php?id='],
        'contacts' => ['Contacts', '/contacts/edit.php?id='],
      ];
    ?>
    <?php foreach ($linkViews as $key => [$label, $urlPrefix]): ?>
      <?php if (!empty($linked[$key])): ?>
        <div>
          <strong><?=h($label)?></strong>
          <?php foreach ($linked[$key] as $row): ?>
            <div><a href="<?=h($urlPrefix . (int)$row['id'])?>"><?=h($row['name'])?></a></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3>Completion History</h3>
  <?php if (empty($completions)): ?>
    <p class="small">Never completed.</p>
  <?php else: ?>
    <table class="list">
      <thead>
        <tr>
          <th>Completed on</th>
          <th>Completed by</th>
          <th>Notes</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($completions as $c): ?>
          <tr>
            <td><?=h(date('M j, Y', strtotime($c['completed_on'])))?></td>
            <td><?=h($c['completed_by_name'] ?? '')?></td>
            <td><?=h($c['notes'] ?? '')?></td>
            <td style="text-align:right;">
              <form method="post" action="/obligations/completion_remove_eval.php" onsubmit="return confirm('Remove this history entry? The schedule will be recomputed.');" data-skip-unsaved-warning>
                <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                <input type="hidden" name="completion_id" value="<?= (int)$c['id'] ?>">
                <input type="hidden" name="obligation_id" value="<?= (int)$obligationId ?>">
                <button class="button small" type="submit">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Danger Zone</h3>
  <form method="post" action="/obligations/remove_eval.php" onsubmit="return confirm('Delete this obligation and its entire completion history? This cannot be undone.');" data-skip-unsaved-warning>
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$obligationId ?>">
    <button class="danger" type="submit">Delete Obligation</button>
  </form>
</div>

<?php footer_html(); ?>
