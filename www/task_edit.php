<?php
require_once __DIR__ . '/partials.php';
require_once __DIR__ . '/lib/TaskManagement.php';
require_once __DIR__ . '/lib/UserManagement.php';
Application::init();
require_login();

$ctx = UserContext::getUserContext();
$taskId = (int)($_GET['id'] ?? 0);

if (!$taskId) {
    $_SESSION['error'] = 'Invalid task ID.';
    header('Location: /upcoming_tasks.php');
    exit;
}

$task = TaskManagement::getTask($taskId);
if (!$task) {
    $_SESSION['error'] = 'Task not found.';
    header('Location: /upcoming_tasks.php');
    exit;
}

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Get form data from session if redirected back with error, otherwise use task data
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$name = $formData['name'] ?? $task['name'];
$instructions = $formData['instructions'] ?? $task['instructions'];
$recurrenceType = $formData['recurrence_type'] ?? $task['recurrence_type'];
$date = $formData['date'] ?? $task['date'];
$completed = !empty($formData['completed']) || !empty($task['completed']);

// Parse annual_date for display
$annualMonth = '';
$annualDay = '';
if ($task['annual_date'] && preg_match('/^(\d{2})-(\d{2})$/', $task['annual_date'], $m)) {
    $annualMonth = $formData['annual_month'] ?? $m[1];
    $annualDay = $formData['annual_day'] ?? $m[2];
}

$dayOfMonth = $formData['day_of_month'] ?? $task['day_of_month'];
$periodicDays = $formData['periodic_days'] ?? $task['periodic_days'];

// Get responsible users
$responsibleUsers = TaskManagement::getResponsibleUsers($taskId);
$responsibleUserIds = array_column($responsibleUsers, 'id');

// Get all users for the responsible users selector
$allUsers = UserManagement::listUsers();

header_html('Edit Task');
?>

<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Edit Task</h2>
    <a href="/task_completions.php?task_id=<?= $taskId ?>" class="btn-secondary">View Completions</a>
  </div>
  
  <?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
  <?php endif; ?>
  
  <?php if ($success): ?>
    <p class="success"><?= h($success) ?></p>
  <?php endif; ?>

  <form method="post" action="/task_edit_eval.php" id="taskForm">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="task_id" value="<?= $taskId ?>">
    
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
      <label>
        <input type="checkbox" name="completed" value="1" <?= $completed ? 'checked' : '' ?>>
        Mark as completed (task no longer needed)
      </label>
    </div>
    
    <div class="form-group">
      <label>Responsible Users</label>
      <div id="responsible-users-list">
        <?php if (empty($responsibleUsers)): ?>
          <p class="text-muted">No users assigned</p>
        <?php else: ?>
          <ul>
            <?php foreach ($responsibleUsers as $user): ?>
              <li><?= h($user['first_name'] . ' ' . $user['last_name']) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <button type="button" id="edit-users-btn" class="btn-secondary">Edit Responsible Users</button>
    </div>
    
    <div class="form-actions">
      <button type="submit" class="btn-primary">Update Task</button>
      <a href="/upcoming_tasks.php" class="btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<!-- Modal for editing responsible users -->
<div id="usersModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3>Edit Responsible Users</h3>
    <div id="modal-error" class="error" style="display:none;"></div>
    <div class="form-group">
      <label for="modal-users">Select Users</label>
      <select id="modal-users" multiple size="10" style="width: 100%;">
        <?php foreach ($allUsers as $user): ?>
          <option value="<?= h($user['id']) ?>" <?= in_array($user['id'], $responsibleUserIds) ? 'selected' : '' ?>>
            <?= h($user['first_name'] . ' ' . $user['last_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small>Hold Ctrl (Cmd on Mac) to select multiple users.</small>
    </div>
    <div class="modal-actions">
      <button type="button" id="save-users-btn" class="btn-primary">Save</button>
      <button type="button" id="cancel-users-btn" class="btn-secondary">Cancel</button>
    </div>
  </div>
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
  
  // Modal handling
  const modal = document.getElementById('usersModal');
  const editBtn = document.getElementById('edit-users-btn');
  const saveBtn = document.getElementById('save-users-btn');
  const cancelBtn = document.getElementById('cancel-users-btn');
  const modalError = document.getElementById('modal-error');
  
  editBtn.addEventListener('click', function() {
    modal.style.display = 'block';
    modalError.style.display = 'none';
  });
  
  cancelBtn.addEventListener('click', function() {
    modal.style.display = 'none';
  });
  
  saveBtn.addEventListener('click', function() {
    const select = document.getElementById('modal-users');
    const selectedIds = Array.from(select.selectedOptions).map(opt => opt.value);
    
    // Send AJAX request
    fetch('/task_users_edit_eval.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'task_id=<?= $taskId ?>&user_ids=' + encodeURIComponent(JSON.stringify(selectedIds)) + '&csrf_token=' + encodeURIComponent('<?= h($_SESSION['csrf_token'] ?? '') ?>')
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update the list
        document.getElementById('responsible-users-list').innerHTML = data.html;
        modal.style.display = 'none';
      } else {
        modalError.textContent = data.error || 'An error occurred';
        modalError.style.display = 'block';
      }
    })
    .catch(error => {
      modalError.textContent = 'An error occurred: ' + error;
      modalError.style.display = 'block';
    });
  });
  
  // Close modal on outside click
  window.addEventListener('click', function(event) {
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  });
});
</script>

<style>
.modal {
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
}

.modal-content {
  background-color: white;
  margin: 10% auto;
  padding: 20px;
  border-radius: 8px;
  width: 80%;
  max-width: 500px;
}

.modal-actions {
  margin-top: 20px;
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.text-muted {
  color: #666;
  font-style: italic;
}
</style>

<?php footer_html(); ?>
