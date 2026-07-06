<?php
require_once __DIR__ . '/partials.php';
require_once __DIR__ . '/lib/TaskManagement.php';
Application::init();
require_login();

$ctx = UserContext::getUserContext();
$completionId = (int)($_GET['id'] ?? 0);

if (!$completionId) {
    $_SESSION['error'] = 'Invalid completion ID.';
    header('Location: /upcoming_tasks.php');
    exit;
}

$completion = TaskManagement::getCompletion($completionId);
if (!$completion) {
    $_SESSION['error'] = 'Completion not found.';
    header('Location: /upcoming_tasks.php');
    exit;
}

$task = TaskManagement::getTask($completion['task_id']);
if (!$task) {
    $_SESSION['error'] = 'Task not found.';
    header('Location: /upcoming_tasks.php');
    exit;
}

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Get form data from session if redirected back with error, otherwise use completion data
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$completedOn = $formData['completed_on'] ?? $completion['completed_on'];
$completedFor = $formData['completed_for'] ?? $completion['completed_for'];
$notes = $formData['notes'] ?? $completion['notes'];

header_html('Edit Task Completion');
?>

<div class="card">
  <h2>Edit Task Completion</h2>
  <p><strong>Task:</strong> <?= h($task['name']) ?></p>
  
  <?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
  <?php endif; ?>
  
  <?php if ($success): ?>
    <p class="success"><?= h($success) ?></p>
  <?php endif; ?>

  <form method="post" action="/task_completion_edit_eval.php">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="completion_id" value="<?= $completionId ?>">
    
    <div class="form-group">
      <label for="completed_on">Completed On *</label>
      <input type="date" id="completed_on" name="completed_on" value="<?= h($completedOn) ?>" required>
    </div>
    
    <div class="form-group">
      <label for="completed_for">Completed For *</label>
      <input type="text" id="completed_for" name="completed_for" value="<?= h($completedFor) ?>" required>
      <small>
        <?php
        switch ($task['recurrence_type']) {
            case 'one-off':
                echo 'Date format: YYYY-MM-DD';
                break;
            case 'annual':
                echo 'Year format: YYYY';
                break;
            case 'monthly':
                echo 'Year-Month format: YYYY-MM';
                break;
            case 'periodic':
                echo 'Date format: YYYY-MM-DD';
                break;
        }
        ?>
      </small>
    </div>
    
    <div class="form-group">
      <label for="notes">Notes</label>
      <textarea id="notes" name="notes" rows="4"><?= h($notes) ?></textarea>
    </div>
    
    <div class="form-actions">
      <button type="submit" class="btn-primary">Update Completion</button>
      <a href="/task_completions.php?task_id=<?= $task['id'] ?>" class="btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php footer_html(); ?>
