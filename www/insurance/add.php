<?php
// Add an insurance policy — form. Evaluates to insurance/add_eval.php.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/InsurancePolicyManagement.php';
Application::init();
require_login();

// One-shot flash + form repopulation from add_eval.php on error
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['error'], $_SESSION['form_data']);

header_html('Add Insurance Policy');
?>
<h2>Add Insurance Policy</h2>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/insurance/add_eval.php" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Policy name
        <input type="text" name="name" value="<?=h($form['name'] ?? '')?>" required>
      </label>
      <label>Category
        <input type="text" name="category" value="<?=h($form['category'] ?? '')?>" list="categorySuggestions">
        <datalist id="categorySuggestions">
          <?php foreach (InsurancePolicyManagement::CATEGORY_SUGGESTIONS as $c): ?>
            <option value="<?=h($c)?>"></option>
          <?php endforeach; ?>
        </datalist>
      </label>
      <label>Insurance company
        <input type="text" name="insurance_company" value="<?=h($form['insurance_company'] ?? '')?>">
      </label>
      <label>Policy number
        <input type="text" name="policy_number" value="<?=h($form['policy_number'] ?? '')?>">
      </label>
      <label>Effective date
        <input type="date" name="effective_date" value="<?=h($form['effective_date'] ?? '')?>">
      </label>
      <label>Expiration date
        <input type="date" name="expiration_date" value="<?=h($form['expiration_date'] ?? '')?>">
      </label>
    </div>
    <label>Notes
      <textarea name="notes" rows="3"><?=h($form['notes'] ?? '')?></textarea>
    </label>

    <div class="actions">
      <button class="primary" type="submit">Create Policy</button>
      <a class="button" href="/insurance/">Cancel</a>
    </div>
  </form>
</div>

<?php footer_html(); ?>
