<?php
// Add a household asset — form. Evaluates to assets/add_eval.php.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
Application::init();
require_login();

// One-shot flash + form repopulation from add_eval.php on error
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['error'], $_SESSION['form_data']);

header_html('Add Asset');
?>
<h2>Add Asset</h2>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/assets/add_eval.php" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Name
        <input type="text" name="name" value="<?=h($form['name'] ?? '')?>" required>
      </label>
      <label>Category
        <input type="text" name="category" value="<?=h($form['category'] ?? '')?>" list="categorySuggestions">
        <datalist id="categorySuggestions">
          <?php foreach (AssetManagement::CATEGORY_SUGGESTIONS as $c): ?>
            <option value="<?=h($c)?>"></option>
          <?php endforeach; ?>
        </datalist>
      </label>
      <label>Purchase date
        <input type="date" name="purchase_date" value="<?=h($form['purchase_date'] ?? '')?>">
      </label>
      <label>Purchase price (optional)
        <input type="number" name="purchase_price" value="<?=h($form['purchase_price'] ?? '')?>" min="0" step="0.01" placeholder="0.00">
      </label>
    </div>
    <label>Description
      <textarea name="description" rows="3"><?=h($form['description'] ?? '')?></textarea>
    </label>
    <label>Warranty information
      <textarea name="warranty_info" rows="3" placeholder="Warranty terms, expiration, where the paperwork lives…"><?=h($form['warranty_info'] ?? '')?></textarea>
    </label>

    <div class="actions">
      <button class="primary" type="submit">Create Asset</button>
      <a class="button" href="/assets/">Cancel</a>
    </div>
    <small class="small">You can add photos after creating the asset.</small>
  </form>
</div>

<?php footer_html(); ?>
