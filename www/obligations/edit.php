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
require_once __DIR__ . '/linked_objects_fragment.php';
Application::init();
require_login();

$obligationId = (int)($_GET['id'] ?? 0);
$obligation = $obligationId > 0 ? ObligationManagement::getObligation($obligationId) : null;
if (!$obligation) {
    $_SESSION['error'] = 'Obligation not found.';
    header('Location: /obligations/');
    exit;
}

$updates = ObligationManagement::listUpdates($obligationId);
$linked = ObligationManagement::getLinkedObjects($obligationId);

// One-shot flash + form repopulation from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['form_data']);

if (!empty($form)) {
    $values = $form;
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
  <h3>Update Obligation</h3>
  <form method="post" action="/obligations/update_eval.php" enctype="multipart/form-data" class="stack" id="updateForm">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="obligation_id" value="<?= (int)$obligationId ?>">
    <input type="hidden" name="return" value="/obligations/edit.php?id=<?= (int)$obligationId ?>">
    <label>Comment
      <textarea name="comment" rows="4" placeholder="Document progress — e.g. Called the county, waiting for a callback. Paid online, confirmation #12345…"></textarea>
    </label>
    <label>Attachment (optional)
      <input type="file" name="attachment">
      <small class="small">Receipts, confirmations, paperwork. PDF, images, Word, Excel, or text — max 20 MB. Stored privately.</small>
    </label>
    <div class="grid" style="grid-template-columns:auto 200px 1fr;gap:12px;align-items:center;">
      <label class="inline">
        <input type="checkbox" name="completed" value="1" id="updateCompleted">
        Mark complete
      </label>
      <input type="date" name="completed_on" value="<?=h($today)?>" id="updateCompletedOn" disabled aria-label="Completed on">
      <span></span>
    </div>
    <div class="actions">
      <button class="primary" type="submit" id="updateBtn">Add Update</button>
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

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <h3>Linked Objects</h3>
    <button type="button" class="button small" id="editLinksBtn">Edit Linked Objects</button>
  </div>
  <div id="linkedObjectsList"><?= render_linked_objects_list($linked) ?></div>
</div>

<!-- Edit Linked Objects modal: saves via AJAX to obligations/links_eval.php -->
<div class="modal hidden" id="linksModal" role="dialog" aria-modal="true" aria-labelledby="linksModalTitle">
  <div class="modal-content" style="max-width:720px;">
    <button class="close" type="button" id="linksModalClose" aria-label="Close">&times;</button>
    <h3 id="linksModalTitle">Edit Linked Objects</h3>
    <p class="small">Attach the records needed to complete this obligation (hold Cmd/Ctrl to select multiple).</p>
    <p class="error hidden" id="linksModalError"></p>
    <form id="linksForm" class="stack">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="obligation_id" value="<?= (int)$obligationId ?>">
      <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
        <?php
          $modalLinkGroups = [
              ['label' => 'Household assets', 'name' => 'link_assets', 'rows' => $opts['assets'], 'selected' => $obligation['linked_asset_ids'], 'nameKey' => 'name'],
              ['label' => 'Documents', 'name' => 'link_documents', 'rows' => $opts['documents'], 'selected' => $obligation['linked_document_ids'], 'nameKey' => 'title'],
              ['label' => 'Insurance policies', 'name' => 'link_policies', 'rows' => $opts['policies'], 'selected' => $obligation['linked_policy_ids'], 'nameKey' => 'name'],
              ['label' => 'Contacts', 'name' => 'link_contacts', 'rows' => $opts['contacts'], 'selected' => $obligation['linked_contact_ids'], 'nameKey' => 'name'],
          ];
        ?>
        <?php foreach ($modalLinkGroups as $g): ?>
          <label><?=h($g['label'])?>
            <select name="<?=h($g['name'])?>[]" multiple size="5">
              <?php foreach ($g['rows'] as $row): ?>
                <option value="<?= (int)$row['id'] ?>" <?= in_array((int)$row['id'], $g['selected'], true) ? 'selected' : '' ?>>
                  <?=h($row[$g['nameKey']])?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="actions">
        <button class="primary" type="submit" id="linksSaveBtn">Save</button>
        <button class="button" type="button" id="linksCancelBtn">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <h3>History</h3>
  <?php if (empty($updates)): ?>
    <p class="small">No updates yet.</p>
  <?php else: ?>
    <table class="list">
      <thead>
        <tr>
          <th>When</th>
          <th>By</th>
          <th>Update</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($updates as $u): ?>
          <tr>
            <td style="white-space:nowrap;"><?=h(date('M j, Y', strtotime($u['created_at'])))?></td>
            <td><?=h($u['created_by_name'] ?? '')?></td>
            <td>
              <?php if ($u['kind'] === 'completion'): ?>
                <span class="badge success">Completed <?=h(date('M j, Y', strtotime($u['completed_on'])))?></span>
                <?php if (!empty($u['notes'])): ?><div><?=nl2br(h($u['notes']))?></div><?php endif; ?>
              <?php else: ?>
                <?php if (!empty($u['completion_id']) && !empty($u['completed_on'])): ?>
                  <span class="badge success">Completed <?=h(date('M j, Y', strtotime($u['completed_on'])))?></span>
                <?php endif; ?>
                <?php if (!empty($u['comment'])): ?><div><?=nl2br(h($u['comment']))?></div><?php endif; ?>
                <?php if (!empty($u['private_file_id'])): ?>
                  <div class="small">📎 <a href="/obligations/attachment.php?comment_id=<?= (int)$u['id'] ?>"><?=h($u['original_filename'] ?? 'Attachment')?></a></div>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td style="text-align:right;">
              <?php if ($u['kind'] === 'completion'): ?>
                <form method="post" action="/obligations/completion_remove_eval.php" onsubmit="return confirm('Remove this completion? The schedule will be recomputed.');" data-skip-unsaved-warning>
                  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                  <input type="hidden" name="completion_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="obligation_id" value="<?= (int)$obligationId ?>">
                  <button class="button small" type="submit">Remove</button>
                </form>
              <?php else: ?>
                <form method="post" action="/obligations/comment_remove_eval.php" onsubmit="return confirm('Remove this update?<?= !empty($u['completion_id']) ? ' It marked the obligation complete — the completion will be removed and the schedule recomputed.' : '' ?><?= !empty($u['private_file_id']) ? ' Its attachment will be permanently deleted.' : '' ?>');" data-skip-unsaved-warning>
                  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                  <input type="hidden" name="comment_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="obligation_id" value="<?= (int)$obligationId ?>">
                  <button class="button small" type="submit">Remove</button>
                </form>
              <?php endif; ?>
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

<script>
  (function(){
    // The completed-on date only applies when "Mark complete" is checked
    var completed = document.getElementById('updateCompleted');
    var completedOn = document.getElementById('updateCompletedOn');
    if (completed && completedOn) {
      function sync() { completedOn.disabled = !completed.checked; }
      completed.addEventListener('change', sync);
      sync();
    }

    // Double-click protection for the update form (it may upload a file)
    var form = document.getElementById('updateForm');
    var btn = document.getElementById('updateBtn');
    if (form && btn) {
      form.addEventListener('submit', function(e) {
        if (btn.disabled) { e.preventDefault(); return; }
        btn.disabled = true;
        btn.textContent = 'Saving...';
      });
    }

    // Edit Linked Objects modal: opens over the page, saves via AJAX to
    // links_eval.php, and swaps in the refreshed list fragment on success.
    var linksModal = document.getElementById('linksModal');
    var linksForm = document.getElementById('linksForm');
    var linksError = document.getElementById('linksModalError');
    var linksSaveBtn = document.getElementById('linksSaveBtn');
    var linksSnapshot = [];

    function linksSelects() {
      return Array.prototype.slice.call(linksForm.querySelectorAll('select'));
    }

    function openLinksModal() {
      // Snapshot selections so Cancel can restore them
      linksSnapshot = linksSelects().map(function(sel) {
        return Array.prototype.slice.call(sel.selectedOptions).map(function(o) { return o.value; });
      });
      linksError.classList.add('hidden');
      linksModal.classList.remove('hidden');
    }

    function closeLinksModal(restore) {
      if (restore) {
        linksSelects().forEach(function(sel, i) {
          Array.prototype.slice.call(sel.options).forEach(function(o) {
            o.selected = linksSnapshot[i].indexOf(o.value) !== -1;
          });
        });
      }
      linksModal.classList.add('hidden');
    }

    document.getElementById('editLinksBtn').addEventListener('click', openLinksModal);
    document.getElementById('linksModalClose').addEventListener('click', function() { closeLinksModal(true); });
    document.getElementById('linksCancelBtn').addEventListener('click', function() { closeLinksModal(true); });
    linksModal.addEventListener('click', function(e) { if (e.target === linksModal) closeLinksModal(true); });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && !linksModal.classList.contains('hidden')) closeLinksModal(true);
    });

    linksForm.addEventListener('submit', function(e) {
      e.preventDefault();
      if (linksSaveBtn.disabled) return;
      linksSaveBtn.disabled = true;
      linksSaveBtn.textContent = 'Saving...';
      linksError.classList.add('hidden');

      fetch('/obligations/links_eval.php', { method: 'POST', body: new FormData(linksForm) })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.success) {
            document.getElementById('linkedObjectsList').innerHTML = data.html;
            closeLinksModal(false);
          } else {
            linksError.textContent = data.error || 'Failed to save linked objects.';
            linksError.classList.remove('hidden');
          }
        })
        .catch(function() {
          linksError.textContent = 'Failed to save linked objects. Please try again.';
          linksError.classList.remove('hidden');
        })
        .finally(function() {
          linksSaveBtn.disabled = false;
          linksSaveBtn.textContent = 'Save';
        });
    });
  })();
</script>

<?php footer_html(); ?>
