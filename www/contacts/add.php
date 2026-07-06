<?php
// Add a contact — form. Evaluates to contacts/add_eval.php.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ContactManagement.php';
Application::init();
require_login();

// One-shot flash + form repopulation from add_eval.php on error
$err = $_SESSION['error'] ?? null;
$form = $_SESSION['form_data'] ?? [];
unset($_SESSION['error'], $_SESSION['form_data']);

$selectedCategories = (array)($form['categories'] ?? []);

header_html('Add Contact');
?>
<h2>Add Contact</h2>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/contacts/add_eval.php" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>Name
        <input type="text" name="name" value="<?=h($form['name'] ?? '')?>" required>
      </label>
      <label>Type
        <select name="contact_type">
          <option value="person" <?= ($form['contact_type'] ?? 'person') === 'person' ? 'selected' : '' ?>>Person</option>
          <option value="organization" <?= ($form['contact_type'] ?? '') === 'organization' ? 'selected' : '' ?>>Organization</option>
        </select>
      </label>
      <label>Organization / Company
        <input type="text" name="organization" value="<?=h($form['organization'] ?? '')?>">
      </label>
      <label>Job title / Role
        <input type="text" name="job_title" value="<?=h($form['job_title'] ?? '')?>">
      </label>
      <label>Phone
        <input type="text" name="phone" value="<?=h($form['phone'] ?? '')?>">
      </label>
      <label>Email
        <input type="email" name="email" value="<?=h($form['email'] ?? '')?>">
      </label>
      <label>Website
        <input type="text" name="website" value="<?=h($form['website'] ?? '')?>" placeholder="https://…">
      </label>
    </div>
    <label>Address
      <textarea name="address" rows="2"><?=h($form['address'] ?? '')?></textarea>
    </label>
    <label>Notes
      <textarea name="notes" rows="3"><?=h($form['notes'] ?? '')?></textarea>
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
      <button class="primary" type="submit">Create Contact</button>
      <a class="button" href="/contacts/">Cancel</a>
    </div>
  </form>
</div>

<?php footer_html(); ?>
