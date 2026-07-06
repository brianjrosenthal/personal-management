<?php
// Add a vault document — form with file upload. Evaluates to documents/add_eval.php.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/DocumentManagement.php';
require_once __DIR__ . '/../lib/UserManagement.php';
Application::init();
require_login();

$users = UserManagement::listUsers();

// One-shot flash + form repopulation from add_eval.php on error
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['error'], $_SESSION['form_data']);

header_html('Add Document');
?>
<h2>Add Document</h2>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/documents/add_eval.php" enctype="multipart/form-data" class="stack" id="documentForm">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Title
        <input type="text" name="title" value="<?=h($form['title'] ?? '')?>" required>
      </label>
      <label>Category
        <input type="text" name="category" value="<?=h($form['category'] ?? '')?>" list="categorySuggestions">
        <datalist id="categorySuggestions">
          <?php foreach (DocumentManagement::CATEGORY_SUGGESTIONS as $c): ?>
            <option value="<?=h($c)?>"></option>
          <?php endforeach; ?>
        </datalist>
      </label>
      <label>Owner (family member)
        <select name="owner_user_id">
          <option value="">— Entire family —</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)($form['owner_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
              <?=h(trim($u['first_name'] . ' ' . $u['last_name']))?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label>Description
      <textarea name="description" rows="3"><?=h($form['description'] ?? '')?></textarea>
    </label>
    <label>File attachment
      <input type="file" name="file" required>
      <small class="small">PDF, images, Word, Excel, or text. Max 20 MB. Stored privately — downloads require login.</small>
    </label>

    <div class="actions">
      <button class="primary" type="submit" id="documentBtn">Upload Document</button>
      <a class="button" href="/documents/">Cancel</a>
    </div>
  </form>
</div>

<script>
  (function(){
    // Double-click protection for the upload button
    var form = document.getElementById('documentForm');
    var btn = document.getElementById('documentBtn');
    if (form && btn) {
      form.addEventListener('submit', function(e) {
        if (btn.disabled) { e.preventDefault(); return; }
        btn.disabled = true;
        btn.textContent = 'Uploading...';
      });
    }
  })();
</script>

<?php footer_html(); ?>
