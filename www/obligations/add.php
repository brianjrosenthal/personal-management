<?php
// Add a recurring obligation — form. Evaluates to obligations/add_eval.php.
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

// One-shot flash + form repopulation from add_eval.php on error
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['error'], $_SESSION['form_data']);

$values = $form + [
    'recurrence_type' => 'date_of_year',
    'recurrence_interval' => '1',
    'reminder_lead_days' => '7',
    'is_active' => 1,
    'linked_asset_ids' => (array)($form['link_assets'] ?? []),
    'linked_document_ids' => (array)($form['link_documents'] ?? []),
    'linked_policy_ids' => (array)($form['link_policies'] ?? []),
    'linked_contact_ids' => (array)($form['link_contacts'] ?? []),
];
// Repopulated forms carry is_active explicitly; a fresh form defaults to active
if (!empty($form) && empty($form['is_active'])) {
    $values['is_active'] = 0;
}

$opts = [
    'users' => UserManagement::listUsers(),
    'assets' => AssetManagement::listAssets(),
    'documents' => DocumentManagement::listDocuments(),
    'policies' => InsurancePolicyManagement::listPolicies(),
    'contacts' => ContactManagement::listContacts(),
];

header_html('Add Obligation');
?>
<h2>Add Obligation</h2>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/obligations/add_eval.php" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <?php render_obligation_form_fields($values, $opts); ?>
    <div class="actions">
      <button class="primary" type="submit">Create Obligation</button>
      <a class="button" href="/obligations/">Cancel</a>
    </div>
  </form>
</div>

<?php footer_html(); ?>
