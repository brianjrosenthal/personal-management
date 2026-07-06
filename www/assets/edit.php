<?php
// Edit a household asset — form + photo management. Evaluates to assets/edit_eval.php;
// photos post to assets/photo_add_eval.php / assets/photo_remove_eval.php.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
require_once __DIR__ . '/../lib/Files.php';
Application::init();
require_login();

$assetId = (int)($_GET['id'] ?? 0);
$asset = $assetId > 0 ? AssetManagement::getAsset($assetId) : null;
if (!$asset) {
    $_SESSION['error'] = 'Asset not found.';
    header('Location: /assets/');
    exit;
}

$photos = AssetManagement::listPhotos($assetId);

// One-shot flash + form repopulation from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['form_data']);

$val = function (string $key) use ($form, $asset) {
    return $form[$key] ?? ($asset[$key] ?? '');
};

header_html('Edit ' . $asset['name']);
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Edit <?=h($asset['name'])?></h2>
  <a class="button" href="/assets/">Back to Assets</a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/assets/edit_eval.php" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$assetId ?>">
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Name
        <input type="text" name="name" value="<?=h($val('name'))?>" required>
      </label>
      <label>Category
        <input type="text" name="category" value="<?=h($val('category'))?>" list="categorySuggestions">
        <datalist id="categorySuggestions">
          <?php foreach (AssetManagement::CATEGORY_SUGGESTIONS as $c): ?>
            <option value="<?=h($c)?>"></option>
          <?php endforeach; ?>
        </datalist>
      </label>
      <label>Purchase date
        <input type="date" name="purchase_date" value="<?=h($val('purchase_date'))?>">
      </label>
      <label>Purchase price (optional)
        <input type="number" name="purchase_price" value="<?=h($val('purchase_price'))?>" min="0" step="0.01" placeholder="0.00">
      </label>
    </div>
    <label>Description
      <textarea name="description" rows="3"><?=h($val('description'))?></textarea>
    </label>
    <label>Warranty information
      <textarea name="warranty_info" rows="3"><?=h($val('warranty_info'))?></textarea>
    </label>

    <div class="actions">
      <button class="primary" type="submit">Save Asset</button>
      <a class="button" href="/assets/">Cancel</a>
    </div>
  </form>
</div>

<div class="card">
  <h3>Photos</h3>
  <?php if (!empty($photos)): ?>
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
      <?php foreach ($photos as $photo): ?>
        <div style="text-align:center;">
          <a href="<?=h(Files::publicFileUrl((int)$photo['public_file_id']))?>" target="_blank">
            <img src="<?=h(Files::publicFileImageUrl((int)$photo['public_file_id'], 140))?>" alt="" style="width:140px;height:140px;object-fit:cover;border-radius:10px;display:block;">
          </a>
          <form method="post" action="/assets/photo_remove_eval.php" onsubmit="return confirm('Remove this photo?');" style="margin-top:6px;">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>">
            <input type="hidden" name="asset_id" value="<?= (int)$assetId ?>">
            <button class="button small" type="submit">Remove</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="small">No photos yet.</p>
  <?php endif; ?>

  <form method="post" action="/assets/photo_add_eval.php" enctype="multipart/form-data" class="stack" id="assetPhotoForm">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="asset_id" value="<?= (int)$assetId ?>">
    <label>Add a photo
      <input type="file" name="photo" accept="image/*" required>
    </label>
    <div class="actions">
      <button class="button" id="assetPhotoBtn" type="submit">Upload Photo</button>
    </div>
  </form>
</div>

<div class="card">
  <h3>Danger Zone</h3>
  <form method="post" action="/assets/remove_eval.php" onsubmit="return confirm('Delete this asset? This cannot be undone.');">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$assetId ?>">
    <button class="danger" type="submit">Delete Asset</button>
  </form>
</div>

<script>
  (function(){
    // Double-click protection for the photo upload button
    var form = document.getElementById('assetPhotoForm');
    var btn = document.getElementById('assetPhotoBtn');
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
