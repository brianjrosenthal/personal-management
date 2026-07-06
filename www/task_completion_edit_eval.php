<?php
require_once __DIR__ . '/partials.php';
require_once __DIR__ . '/lib/TaskManagement.php';
Application::init();
require_login();

$ctx = UserContext::getUserContext();

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Invalid request. Please try again.';
    header('Location: /upcoming_tasks.php');
    exit;
}

$completionId = (int)($_POST['completion_id'] ?? 0);
if (!$completionId) {
    $_SESSION['error'] = 'Invalid completion ID.';
    header('Location: /upcoming_tasks.php');
    exit;
}

// Verify completion exists
$completion = TaskManagement::getCompletion($completionId);
if (!$completion) {
    $_SESSION['error'] = 'Completion not found.';
    header('Location: /upcoming_tasks.php');
    exit;
}

try {
    $completedOn = $_POST['completed_on'] ?? '';
    $completedFor = $_POST['completed_for'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
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
    
    // Update the completion
    TaskManagement::updateCompletion($ctx, $completionId, $completedOn, $completedFor, $notes);
    
    // Success - redirect back to edit page
    $_SESSION['success'] = 'Completion updated successfully.';
    header('Location: /task_completion_edit.php?id=' . $completionId);
    exit;
    
} catch (Exception $e) {
    // Error - save form data and redirect back to edit page
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /task_completion_edit.php?id=' . $completionId);
    exit;
}
