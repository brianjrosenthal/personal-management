<?php
// Document Vault — list and search, with two views: all documents and grouped
// by category.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/DocumentManagement.php';
Application::init();
require_login();

$search = trim($_GET['q'] ?? '');
$view = $_GET['view'] ?? 'category';
if (!in_array($view, ['all', 'category'], true)) {
    $view = 'category';
}

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

function documents_view_url(string $view, string $search): string {
    $params = [];
    if ($view !== 'category') $params['view'] = $view; // category is the default
    if ($search !== '') $params['q'] = $search;
    return '/documents/' . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Table shared by both views. $showCategory lets the category view drop its
// redundant column.
function documents_table(array $rows, bool $showCategory = true): void {
?>
    <table class="list">
      <thead>
        <tr>
          <th>Title</th>
          <?php if ($showCategory): ?><th>Category</th><?php endif; ?>
          <th>Owner</th>
          <th>File</th>
          <th>Uploaded</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $doc): ?>
          <tr>
            <td>
              <a href="/documents/edit.php?id=<?= (int)$doc['id'] ?>"><?=h($doc['title'])?></a>
              <?php if (!empty($doc['description'])): ?>
                <div class="small"><?=h(mb_strimwidth($doc['description'], 0, 90, '…'))?></div>
              <?php endif; ?>
            </td>
            <?php if ($showCategory): ?><td><?=h($doc['category'] ?? '')?></td><?php endif; ?>
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
<?php
}

// Alphabetical category groups, "Uncategorized" last.
function group_documents_by_category(array $documents): array {
    $groups = [];
    $uncategorized = [];
    foreach ($documents as $doc) {
        $category = trim((string)($doc['category'] ?? ''));
        if ($category === '') {
            $uncategorized[] = $doc;
        } else {
            $groups[$category][] = $doc;
        }
    }
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
    if (!empty($uncategorized)) $groups['Uncategorized'] = $uncategorized;
    return $groups;
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
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
    <a class="button small <?= $view === 'all' ? 'primary' : '' ?>" href="<?=h(documents_view_url('all', $search))?>">All Documents</a>
    <a class="button small <?= $view === 'category' ? 'primary' : '' ?>" href="<?=h(documents_view_url('category', $search))?>">By Category</a>
  </div>
  <form method="get" data-auto-submit>
    <?php if ($view !== 'category'): ?><input type="hidden" name="view" value="<?=h($view)?>"><?php endif; ?>
    <label>Search
      <input type="text" name="q" value="<?=h($search)?>" placeholder="Title, category, or description">
    </label>
  </form>
</div>

<?php if (empty($documents)): ?>
  <p class="small"><?= $search !== '' ? 'No documents match your search.' : 'No documents yet. Store wills, deeds, passports, tax returns, and other important papers here.' ?></p>
<?php elseif ($view === 'category'): ?>
  <?php foreach (group_documents_by_category($documents) as $label => $rows): ?>
    <div class="card">
      <h3><?=h($label)?> <span class="small">(<?= count($rows) ?>)</span></h3>
      <?php documents_table($rows, false); ?>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="card">
    <?php documents_table($documents); ?>
  </div>
<?php endif; ?>

<?php footer_html(); ?>
