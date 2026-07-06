<?php
require_once __DIR__ . '/partials.php';
require_once __DIR__ . '/lib/TaskManagement.php';
Application::init();
require_login();

header('Content-Type: application/json');

$ctx = UserContext::getUserContext();

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid request. Please try again.']);
    exit;
}

try {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $completedOn = $_POST['completed_on'] ?? '';
    $completedFor = $_POST['completed_for'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (!$taskId) {
        throw new InvalidArgumentException('Invalid task ID.');
    }
    
    // Verify task exists
    $task = TaskManagement::getTask($taskId);
    if (!$task) {
        throw new InvalidArgumentException('Task not found.');
    }
    
    if (empty($completedOn)) {
        throw new InvalidArgumentException('Completed on date is required.');
    }
    
    if (empty($completedFor)) {
        throw new InvalidArgumentException('Completed for value is required.');
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $completedOn)) {
        throw new InvalidArgumentException('Invalid completed on date format.');
    }
    
    // Add the completion
    TaskManagement::addCompletion($ctx, $taskId, $completedOn, $completedFor, $notes);
    
    echo json_encode(['success' => true]);
    exit;
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
