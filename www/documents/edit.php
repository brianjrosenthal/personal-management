<?php
// Edit a vault document — form. Evaluates to documents/edit_eval.php.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/DocumentManagement.php';
require_once __DIR__ . '/../lib/UserManagement.php';
Application::init();
require_login();

$docId = (int)($_GET['id'] ?? 0);
$doc = $docId > 0 ? DocumentManagement::getDocument($docId) : null;
if (!$doc) {
    $_SESSION['error'] = 'Document not found.';
    header('Location: /documents/');
    exit;
}

$users = UserManagement::listUsers();

// One-shot flash + form repopulation from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['form_data']);

$val = function (string $key) use ($form, $doc) {
    return $form[$key] ?? ($doc[$key] ?? '');
};
$ownerSelected = (int)($form['owner_user_id'] ?? $doc['owner_user_id'] ?? 0);

header_html('Edit ' . $doc['title']);
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Edit <?=h($doc['title'])?></h2>
  <a class="button" href="/documents/">Back to Documents</a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/documents/edit_eval.php" enctype="multipart/form-data" class="stack" id="documentForm">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$docId ?>">
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Title
        <input type="text" name="title" value="<?=h($val('title'))?>" required>
      </label>
      <label>Category
        <input type="text" name="category" value="<?=h($val('category'))?>" list="categorySuggestions">
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
            <option value="<?= (int)$u['id'] ?>" <?= $ownerSelected === (int)$u['id'] ? 'selected' : '' ?>>
              <?=h(trim($u['first_name'] . ' ' . $u['last_name']))?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label>Description
      <textarea name="description" rows="3"><?=h($val('description'))?></textarea>
    </label>

    <div>
      <strong>Current file:</strong>
      <?php if (!empty($doc['private_file_id'])): ?>
        <a href="/documents/download.php?id=<?= (int)$docId ?>"><?=h($doc['original_filename'] ?? 'Download')?></a>
      <?php else: ?>
        <span class="small">None</span>
      <?php endif; ?>
    </div>
    <label>Replace file (optional)
      <input type="file" name="file">
      <small class="small">Uploading a new file permanently replaces the current one.</small>
    </label>

    <div class="actions">
      <button class="primary" type="submit" id="documentBtn">Save Document</button>
      <a class="button" href="/documents/">Cancel</a>
    </div>
  </form>
</div>

<div class="card">
  <h3>Danger Zone</h3>
  <form method="post" action="/documents/remove_eval.php" onsubmit="return confirm('Delete this document and its file? This cannot be undone.');">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$docId ?>">
    <button class="danger" type="submit">Delete Document</button>
  </form>
</div>

<script>
  (function(){
    // Double-click protection for the save button
    var form = document.getElementById('documentForm');
    var btn = document.getElementById('documentBtn');
    if (form && btn) {
      form.addEventListener('submit', function(e) {
        if (btn.disabled) { e.preventDefault(); return; }
        btn.disabled = true;
        btn.textContent = 'Saving...';
      });
    }
  })();
</script>

<?php footer_html(); ?>
