<?php
// Document Vault — list and search.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/DocumentManagement.php';
Application::init();
require_login();

$search = trim($_GET['q'] ?? '');
$documents = DocumentManagement::listDocuments($search);

// One-shot flash from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

function format_bytes(?int $bytes): string {
    if ($bytes === null || $bytes <= 0) return '';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024) . ' KB';
    return $bytes . ' B';
}

header_html('Document Vault');
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Document Vault</h2>
  <a class="button primary" href="/documents/add.php">Add Document</a>
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

<?php if (empty($documents)): ?>
  <p class="small"><?= $search !== '' ? 'No documents match your search.' : 'No documents yet. Store wills, deeds, passports, tax returns, and other important papers here.' ?></p>
<?php else: ?>
  <div class="card">
    <table class="list">
      <thead>
        <tr>
          <th>Title</th>
          <th>Category</th>
          <th>Owner</th>
          <th>File</th>
          <th>Uploaded</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($documents as $doc): ?>
          <tr>
            <td>
              <a href="/documents/edit.php?id=<?= (int)$doc['id'] ?>"><?=h($doc['title'])?></a>
              <?php if (!empty($doc['description'])): ?>
                <div class="small"><?=h(mb_strimwidth($doc['description'], 0, 90, '…'))?></div>
              <?php endif; ?>
            </td>
            <td><?=h($doc['category'] ?? '')?></td>
            <td><?=h($doc['owner_name'] ?? '')?></td>
            <td>
              <?php if (!empty($doc['private_file_id'])): ?>
                <a href="/documents/download.php?id=<?= (int)$doc['id'] ?>"><?=h($doc['original_filename'] ?? 'Download')?></a>
                <span class="small"><?=h(format_bytes($doc['byte_length'] !== null ? (int)$doc['byte_length'] : null))?></span>
              <?php else: ?>
                <span class="small">No file</span>
              <?php endif; ?>
            </td>
            <td><?=h(date('M j, Y', strtotime($doc['created_at'])))?></td>
            <td class="small" style="text-align:right;">
              <a class="button small" href="/documents/edit.php?id=<?= (int)$doc['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php footer_html(); ?>
