<?php
// Edit an insurance policy — form. Evaluates to insurance/edit_eval.php.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/InsurancePolicyManagement.php';
Application::init();
require_login();

$policyId = (int)($_GET['id'] ?? 0);
$policy = $policyId > 0 ? InsurancePolicyManagement::getPolicy($policyId) : null;
if (!$policy) {
    $_SESSION['error'] = 'Policy not found.';
    header('Location: /insurance/');
    exit;
}

// One-shot flash + form repopulation from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['form_data']);

$val = function (string $key) use ($form, $policy) {
    return $form[$key] ?? ($policy[$key] ?? '');
};

header_html('Edit ' . $policy['name']);
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Edit <?=h($policy['name'])?></h2>
  <a class="button" href="/insurance/">Back to Policies</a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/insurance/edit_eval.php" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$policyId ?>">
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Policy name
        <input type="text" name="name" value="<?=h($val('name'))?>" required>
      </label>
      <label>Category
        <input type="text" name="category" value="<?=h($val('category'))?>" list="categorySuggestions">
        <datalist id="categorySuggestions">
          <?php foreach (InsurancePolicyManagement::CATEGORY_SUGGESTIONS as $c): ?>
            <option value="<?=h($c)?>"></option>
          <?php endforeach; ?>
        </datalist>
      </label>
      <label>Insurance company
        <input type="text" name="insurance_company" value="<?=h($val('insurance_company'))?>">
      </label>
      <label>Policy number
        <input type="text" name="policy_number" value="<?=h($val('policy_number'))?>">
      </label>
      <label>Effective date
        <input type="date" name="effective_date" value="<?=h($val('effective_date'))?>">
      </label>
      <label>Expiration date
        <input type="date" name="expiration_date" value="<?=h($val('expiration_date'))?>">
      </label>
    </div>
    <label>Notes
      <textarea name="notes" rows="3"><?=h($val('notes'))?></textarea>
    </label>

    <div class="actions">
      <button class="primary" type="submit">Save Policy</button>
      <a class="button" href="/insurance/">Cancel</a>
    </div>
  </form>
</div>

<div class="card">
  <h3>Danger Zone</h3>
  <form method="post" action="/insurance/remove_eval.php" onsubmit="return confirm('Delete this policy? This cannot be undone.');">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$policyId ?>">
    <button class="danger" type="submit">Delete Policy</button>
  </form>
</div>

<?php footer_html(); ?>
