<?php
require_once __DIR__ . '/partials.php';
require_once __DIR__ . '/lib/TaskManagement.php';
Application::init();
require_login();

$ctx = UserContext::getUserContext();
$taskId = (int)($_GET['task_id'] ?? 0);

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

// Get all completions for this task
$completions = TaskManagement::listCompletions($taskId);

header_html('Task Completions');
?>

<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2><?= h($task['name']) ?> - Completions</h2>
    <a href="/task_edit.php?id=<?= $taskId ?>" class="btn-secondary">Back to Task</a>
  </div>
  
  <?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
  <?php endif; ?>
  
  <?php if ($success): ?>
    <p class="success"><?= h($success) ?></p>
  <?php endif; ?>

  <div style="margin-bottom: 20px;">
    <p><strong>Task Type:</strong> <?= h(ucfirst($task['recurrence_type'])) ?></p>
    <?php if ($task['instructions']): ?>
      <p><strong>Instructions:</strong> <?= h($task['instructions']) ?></p>
    <?php endif; ?>
  </div>

  <?php if (!empty($completions)): ?>
    <table class="completions-table">
      <thead>
        <tr>
          <th>Completed On</th>
          <th>Completed For</th>
          <th>Completed By</th>
          <th>Notes</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($completions as $completion): ?>
          <tr>
            <td><?= h(date('M j, Y', strtotime($completion['completed_on']))) ?></td>
            <td><?= h($completion['completed_for']) ?></td>
            <td>
              <?php if ($completion['first_name'] && $completion['last_name']): ?>
                <?= h($completion['first_name'] . ' ' . $completion['last_name']) ?>
              <?php else: ?>
                <span class="text-muted">Unknown</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($completion['notes']): ?>
                <div class="notes-preview" title="<?= h($completion['notes']) ?>">
                  <?= h(strlen($completion['notes']) > 100 ? substr($completion['notes'], 0, 100) . '...' : $completion['notes']) ?>
                </div>
              <?php else: ?>
                <span class="text-muted">No notes</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="/task_completion_edit.php?id=<?= $completion['id'] ?>" class="btn-small">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="text-muted" style="text-align: center; padding: 40px;">
      No completions recorded for this task yet.
    </p>
  <?php endif; ?>
</div>

<style>
.completions-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}

.completions-table th,
.completions-table td {
  padding: 10px;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.completions-table th {
  background-color: #f5f5f5;
  font-weight: bold;
}

.completions-table tr:hover {
  background-color: #f9f9f9;
}

.btn-small {
  padding: 5px 10px;
  font-size: 0.9em;
  margin-right: 5px;
}

.text-muted {
  color: #666;
  font-style: italic;
}

.notes-preview {
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: help;
}
</style>

<?php footer_html(); ?>
