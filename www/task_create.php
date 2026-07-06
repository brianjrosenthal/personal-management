<?php
require_once __DIR__ . '/partials.php';
require_once __DIR__ . '/lib/TaskManagement.php';
require_once __DIR__ . '/lib/UserManagement.php';
Application::init();
require_login();

$ctx = UserContext::getUserContext();
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Get form data from session if redirected back with error
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$name = $formData['name'] ?? '';
$instructions = $formData['instructions'] ?? '';
$recurrenceType = $formData['recurrence_type'] ?? 'one-off';
$date = $formData['date'] ?? '';
$annualMonth = $formData['annual_month'] ?? '';
$annualDay = $formData['annual_day'] ?? '';
$dayOfMonth = $formData['day_of_month'] ?? '';
$periodicDays = $formData['periodic_days'] ?? '';
$responsibleUserIds = $formData['responsible_user_ids'] ?? [];

// Get all users for the responsible users selector
$allUsers = UserManagement::listUsers();

header_html('Create Task');
?>

<div class="card">
  <h2>Create Task</h2>
  
  <?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
  <?php endif; ?>
  
  <?php if ($success): ?>
    <p class="success"><?= h($success) ?></p>
  <?php endif; ?>

  <form method="post" action="/task_create_eval.php" id="taskForm">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
    
    <div class="form-group">
      <label for="name">Task Name *</label>
      <input type="text" id="name" name="name" value="<?= h($name) ?>" required autofocus>
    </div>
    
    <div class="form-group">
      <label for="instructions">Instructions</label>
      <textarea id="instructions" name="instructions" rows="4"><?= h($instructions) ?></textarea>
    </div>
    
    <div class="form-group">
      <label for="recurrence_type">Recurrence Type *</label>
      <select id="recurrence_type" name="recurrence_type" required>
        <option value="one-off" <?= $recurrenceType === 'one-off' ? 'selected' : '' ?>>One-off</option>
        <option value="annual" <?= $recurrenceType === 'annual' ? 'selected' : '' ?>>Annual</option>
        <option value="monthly" <?= $recurrenceType === 'monthly' ? 'selected' : '' ?>>Monthly</option>
        <option value="periodic" <?= $recurrenceType === 'periodic' ? 'selected' : '' ?>>Periodic</option>
      </select>
    </div>
    
    <!-- One-off date field -->
    <div class="form-group recurrence-field" id="oneoff-field" style="display:none;">
      <label for="date">Due Date *</label>
      <input type="date" id="date" name="date" value="<?= h($date) ?>">
    </div>
    
    <!-- Annual date fields -->
    <div class="form-group recurrence-field" id="annual-field" style="display:none;">
      <label>Annual Date (Month/Day) *</label>
      <div style="display: flex; gap: 10px;">
        <select id="annual_month" name="annual_month" style="flex: 1;">
          <option value="">Month</option>
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= sprintf('%02d', $m) ?>" <?= $annualMonth === sprintf('%02d', $m) ? 'selected' : '' ?>>
              <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
            </option>
          <?php endfor; ?>
        </select>
        <select id="annual_day" name="annual_day" style="flex: 1;">
          <option value="">Day</option>
          <?php for ($d = 1; $d <= 31; $d++): ?>
            <option value="<?= sprintf('%02d', $d) ?>" <?= $annualDay === sprintf('%02d', $d) ? 'selected' : '' ?>>
              <?= $d ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
    
    <!-- Monthly day field -->
    <div class="form-group recurrence-field" id="monthly-field" style="display:none;">
      <label for="day_of_month">Day of Month (1-31) *</label>
      <input type="number" id="day_of_month" name="day_of_month" min="1" max="31" value="<?= h($dayOfMonth) ?>">
      <small>If a month has fewer days, the last day of the month will be used.</small>
    </div>
    
    <!-- Periodic days field -->
    <div class="form-group recurrence-field" id="periodic-field" style="display:none;">
      <label for="periodic_days">Number of Days Between Occurrences *</label>
      <input type="number" id="periodic_days" name="periodic_days" min="1" value="<?= h($periodicDays) ?>">
    </div>
    
    <div class="form-group">
      <label for="responsible_users">Responsible Users</label>
      <select id="responsible_users" name="responsible_user_ids[]" multiple size="8">
        <?php foreach ($allUsers as $user): ?>
          <option value="<?= h($user['id']) ?>" <?= in_array($user['id'], $responsibleUserIds) ? 'selected' : '' ?>>
            <?= h($user['first_name'] . ' ' . $user['last_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small>Hold Ctrl (Cmd on Mac) to select multiple users.</small>
    </div>
    
    <div class="form-actions">
      <button type="submit" class="btn-primary">Create Task</button>
      <a href="/upcoming_tasks.php" class="btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const recurrenceType = document.getElementById('recurrence_type');
  const allFields = document.querySelectorAll('.recurrence-field');
  
  function updateVisibleFields() {
    const selectedType = recurrenceType.value;
    
    // Hide all recurrence-specific fields first
    allFields.forEach(field => {
      field.style.display = 'none';
      // Remove required attribute from hidden fields
      const inputs = field.querySelectorAll('input, select');
      inputs.forEach(input => {
        if (input.id !== 'recurrence_type') {
          input.removeAttribute('required');
        }
      });
    });
    
    // Show and mark required the appropriate field
    let fieldId = '';
    switch (selectedType) {
      case 'one-off':
        fieldId = 'oneoff-field';
        document.getElementById('date').setAttribute('required', 'required');
        break;
      case 'annual':
        fieldId = 'annual-field';
        document.getElementById('annual_month').setAttribute('required', 'required');
        document.getElementById('annual_day').setAttribute('required', 'required');
        break;
      case 'monthly':
        fieldId = 'monthly-field';
        document.getElementById('day_of_month').setAttribute('required', 'required');
        break;
      case 'periodic':
        fieldId = 'periodic-field';
        document.getElementById('periodic_days').setAttribute('required', 'required');
        break;
    }
    
    if (fieldId) {
      document.getElementById(fieldId).style.display = 'block';
    }
  }
  
  // Set initial state
  updateVisibleFields();
  
  // Update on change
  recurrenceType.addEventListener('change', updateVisibleFields);
});
</script>

<?php footer_html(); ?>
