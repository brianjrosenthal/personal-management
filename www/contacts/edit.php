<?php
// Edit a contact — form. Evaluates to contacts/edit_eval.php.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ContactManagement.php';
Application::init();
require_login();

$contactId = (int)($_GET['id'] ?? 0);
$contact = $contactId > 0 ? ContactManagement::getContact($contactId) : null;
if (!$contact) {
    $_SESSION['error'] = 'Contact not found.';
    header('Location: /contacts/');
    exit;
}

// One-shot flash + form repopulation from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['form_data']);

$val = function (string $key) use ($form, $contact) {
    return $form[$key] ?? ($contact[$key] ?? '');
};
$selectedCategories = !empty($form) ? (array)($form['categories'] ?? []) : $contact['categories'];

header_html('Edit ' . $contact['name']);
?>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Edit <?=h($contact['name'])?></h2>
  <a class="button" href="/contacts/">Back to Contacts</a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/contacts/edit_eval.php" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$contactId ?>">
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Name
        <input type="text" name="name" value="<?=h($val('name'))?>" required>
      </label>
      <label>Type
        <select name="contact_type">
          <option value="person" <?= $val('contact_type') === 'person' ? 'selected' : '' ?>>Person</option>
          <option value="organization" <?= $val('contact_type') === 'organization' ? 'selected' : '' ?>>Organization</option>
        </select>
      </label>
      <label>Organization / Company
        <input type="text" name="organization" value="<?=h($val('organization'))?>">
      </label>
      <label>Job title / Role
        <input type="text" name="job_title" value="<?=h($val('job_title'))?>">
      </label>
      <label>Phone
        <input type="text" name="phone" value="<?=h($val('phone'))?>">
      </label>
      <label>Email
        <input type="email" name="email" value="<?=h($val('email'))?>">
      </label>
      <label>Website
        <input type="text" name="website" value="<?=h($val('website'))?>" placeholder="https://…">
      </label>
    </div>
    <label>Address
      <textarea name="address" rows="2"><?=h($val('address'))?></textarea>
    </label>
    <label>Notes
      <textarea name="notes" rows="3"><?=h($val('notes'))?></textarea>
    </label>

    <h3>Categories / Roles</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:6px;">
      <?php foreach (ContactManagement::CATEGORIES as $c): ?>
        <label class="inline">
          <input type="checkbox" name="categories[]" value="<?=h($c)?>" <?= in_array($c, $selectedCategories, true) ? 'checked' : '' ?>>
          <?=h($c)?>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="actions">
      <button class="primary" type="submit">Save Contact</button>
      <a class="button" href="/contacts/">Cancel</a>
    </div>
  </form>
</div>

<div class="card">
  <h3>Danger Zone</h3>
  <form method="post" action="/contacts/remove_eval.php" onsubmit="return confirm('Delete this contact? This cannot be undone.');">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$contactId ?>">
    <button class="danger" type="submit">Delete Contact</button>
  </form>
</div>

<?php footer_html(); ?>
