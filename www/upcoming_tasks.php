<?php
require_once __DIR__ . '/partials.php';
require_once __DIR__ . '/lib/TaskManagement.php';
Application::init();
require_login();

$ctx = UserContext::getUserContext();
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Get all active tasks
$tasks = TaskManagement::listTasks(false);

// Group tasks by status
$pastDueTasks = [];
$dueSoonTasks = [];
$currentTasks = [];
$completedTasks = [];
$otherTasks = [];

foreach ($tasks as $task) {
    switch ($task['status']) {
        case 'Past Due':
            $pastDueTasks[] = $task;
            break;
        case 'Due soon':
            $dueSoonTasks[] = $task;
            break;
        case 'Current':
        case 'Completed':
            // "Current" for recurring tasks that are up to date
            // "Completed" for one-off tasks
            if ($task['status'] === 'Completed') {
                $completedTasks[] = $task;
            } else {
                $currentTasks[] = $task;
            }
            break;
        default:
            $otherTasks[] = $task;
            break;
    }
}

// Sort by due date where available
$sortByDueDate = function($a, $b) {
    if (!isset($a['due_date']) || !isset($b['due_date'])) {
        return 0;
    }
    return strcmp($a['due_date'], $b['due_date']);
};

usort($pastDueTasks, $sortByDueDate);
usort($dueSoonTasks, $sortByDueDate);
usort($currentTasks, $sortByDueDate);

header_html('Upcoming Tasks');
?>

<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Upcoming Tasks</h2>
    <a href="/task_create.php" class="btn-primary">+ Create New Task</a>
  </div>
  
  <?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
  <?php endif; ?>
  
  <?php if ($success): ?>
    <p class="success"><?= h($success) ?></p>
  <?php endif; ?>

  <?php if (!empty($pastDueTasks)): ?>
    <h3 style="color: #d32f2f; margin-top: 30px;">Past Due Tasks</h3>
    <table class="task-table">
      <thead>
        <tr>
          <th>Task Name</th>
          <th>Due Date</th>
          <th>Days Overdue</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pastDueTasks as $task): ?>
          <tr>
            <td>
              <a href="/task_edit.php?id=<?= $task['id'] ?>"><?= h($task['name']) ?></a>
            </td>
            <td><?= h($task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'N/A') ?></td>
            <td><?= h(abs($task['days_until_due'] ?? 0)) ?> days</td>
            <td>
              <a href="/task_edit.php?id=<?= $task['id'] ?>" class="btn-small">Edit</a>
              <button type="button" class="btn-small btn-primary" onclick="showCompleteModal(<?= $task['id'] ?>, '<?= h($task['name']) ?>', '<?= h($task['recurrence_type']) ?>')">Log Complete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (!empty($dueSoonTasks)): ?>
    <h3 style="color: #f57c00; margin-top: 30px;">Due Soon Tasks</h3>
    <table class="task-table">
      <thead>
        <tr>
          <th>Task Name</th>
          <th>Due Date</th>
          <th>Days Until Due</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dueSoonTasks as $task): ?>
          <tr>
            <td>
              <a href="/task_edit.php?id=<?= $task['id'] ?>"><?= h($task['name']) ?></a>
            </td>
            <td><?= h($task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'N/A') ?></td>
            <td><?= h($task['days_until_due']) ?> days</td>
            <td>
              <a href="/task_edit.php?id=<?= $task['id'] ?>" class="btn-small">Edit</a>
              <button type="button" class="btn-small btn-primary" onclick="showCompleteModal(<?= $task['id'] ?>, '<?= h($task['name']) ?>', '<?= h($task['recurrence_type']) ?>')">Log Complete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (!empty($currentTasks)): ?>
    <h3 style="color: #388e3c; margin-top: 30px;">Current Tasks</h3>
    <table class="task-table">
      <thead>
        <tr>
          <th>Task Name</th>
          <th>Due Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($currentTasks as $task): ?>
          <tr>
            <td>
              <a href="/task_edit.php?id=<?= $task['id'] ?>"><?= h($task['name']) ?></a>
            </td>
            <td><?= h($task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'N/A') ?></td>
            <td><?= h($task['status']) ?></td>
            <td>
              <a href="/task_edit.php?id=<?= $task['id'] ?>" class="btn-small">Edit</a>
              <button type="button" class="btn-small btn-primary" onclick="showCompleteModal(<?= $task['id'] ?>, '<?= h($task['name']) ?>', '<?= h($task['recurrence_type']) ?>')">Log Complete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (!empty($completedTasks)): ?>
    <h3 style="color: #757575; margin-top: 30px;">Completed Tasks</h3>
    <table class="task-table">
      <thead>
        <tr>
          <th>Task Name</th>
          <th>Type</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($completedTasks as $task): ?>
          <tr>
            <td>
              <a href="/task_edit.php?id=<?= $task['id'] ?>"><?= h($task['name']) ?></a>
            </td>
            <td><?= h(ucfirst($task['recurrence_type'])) ?></td>
            <td>
              <a href="/task_edit.php?id=<?= $task['id'] ?>" class="btn-small">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (!empty($otherTasks)): ?>
    <h3 style="margin-top: 30px;">Other Tasks</h3>
    <table class="task-table">
      <thead>
        <tr>
          <th>Task Name</th>
          <th>Status</th>
          <th>Due Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($otherTasks as $task): ?>
          <tr>
            <td>
              <a href="/task_edit.php?id=<?= $task['id'] ?>"><?= h($task['name']) ?></a>
            </td>
            <td><?= h($task['status']) ?></td>
            <td><?= h($task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'N/A') ?></td>
            <td>
              <a href="/task_edit.php?id=<?= $task['id'] ?>" class="btn-small">Edit</a>
              <button type="button" class="btn-small btn-primary" onclick="showCompleteModal(<?= $task['id'] ?>, '<?= h($task['name']) ?>', '<?= h($task['recurrence_type']) ?>')">Log Complete</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (empty($tasks)): ?>
    <p class="text-muted" style="text-align: center; padding: 40px;">
      No tasks yet. <a href="/task_create.php">Create your first task</a>.
    </p>
  <?php endif; ?>
</div>

<!-- Modal for marking task complete -->
<div id="completeModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3>Mark Task Complete</h3>
    <p><strong id="modal-task-name"></strong></p>
    <div id="modal-error" class="error" style="display:none;"></div>
    
    <form id="completeForm">
      <input type="hidden" id="modal-task-id" name="task_id">
      <input type="hidden" id="modal-recurrence-type" name="recurrence_type">
      
      <div class="form-group">
        <label for="modal-completed-on">Completed On *</label>
        <input type="date" id="modal-completed-on" name="completed_on" required>
      </div>
      
      <div class="form-group">
        <label for="modal-completed-for">Completed For *</label>
        <input type="text" id="modal-completed-for" name="completed_for" required>
        <small id="completed-for-help">Format depends on task type</small>
      </div>
      
      <div class="form-group">
        <label for="modal-notes">Notes</label>
        <textarea id="modal-notes" name="notes" rows="3"></textarea>
      </div>
      
      <div class="modal-actions">
        <button type="submit" class="btn-primary">Save</button>
        <button type="button" id="cancel-complete-btn" class="btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<style>
.task-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}

.task-table th,
.task-table td {
  padding: 10px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.task-table th {
  background-color: #f5f5f5;
  font-weight: bold;
}

.task-table tr:hover {
  background-color: #f9f9f9;
}

.btn-small {
  padding: 5px 10px;
  font-size: 0.9em;
  margin-right: 5px;
}

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
  margin: 5% auto;
  padding: 20px;
  border-radius: 8px;
  width: 90%;
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

<script>
const modal = document.getElementById('completeModal');
const modalError = document.getElementById('modal-error');
const cancelBtn = document.getElementById('cancel-complete-btn');
const completeForm = document.getElementById('completeForm');

function showCompleteModal(taskId, taskName, recurrenceType) {
  document.getElementById('modal-task-id').value = taskId;
  document.getElementById('modal-task-name').textContent = taskName;
  document.getElementById('modal-recurrence-type').value = recurrenceType;
  
  // Set default completed_on to today
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('modal-completed-on').value = today;
  
  // Set default completed_for based on recurrence type
  let completedFor = '';
  let helpText = '';
  
  switch (recurrenceType) {
    case 'one-off':
      completedFor = today;
      helpText = 'Date format: YYYY-MM-DD';
      break;
    case 'annual':
      completedFor = new Date().getFullYear().toString();
      helpText = 'Year format: YYYY';
      break;
    case 'monthly':
      completedFor = today.substring(0, 7); // YYYY-MM
      helpText = 'Year-Month format: YYYY-MM';
      break;
    case 'periodic':
      completedFor = today;
      helpText = 'Date format: YYYY-MM-DD';
      break;
  }
  
  document.getElementById('modal-completed-for').value = completedFor;
  document.getElementById('completed-for-help').textContent = helpText;
  
  document.getElementById('modal-notes').value = '';
  modalError.style.display = 'none';
  modal.style.display = 'block';
}

cancelBtn.addEventListener('click', function() {
  modal.style.display = 'none';
});

completeForm.addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(completeForm);
  formData.append('csrf_token', '<?= h($_SESSION['csrf_token'] ?? '') ?>');
  
  fetch('/task_complete_eval.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Reload the page to show updated status
      window.location.reload();
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
</script>

<?php footer_html(); ?>
