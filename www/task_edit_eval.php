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

$taskId = (int)($_POST['task_id'] ?? 0);
if (!$taskId) {
    $_SESSION['error'] = 'Invalid task ID.';
    header('Location: /upcoming_tasks.php');
    exit;
}

// Verify task exists
$task = TaskManagement::getTask($taskId);
if (!$task) {
    $_SESSION['error'] = 'Task not found.';
    header('Location: /upcoming_tasks.php');
    exit;
}

try {
    $recurrenceType = $_POST['recurrence_type'] ?? 'one-off';
    
    // Prepare data array
    $data = [
        'name' => $_POST['name'] ?? '',
        'instructions' => $_POST['instructions'] ?? '',
        'recurrence_type' => $recurrenceType,
        'completed' => !empty($_POST['completed'])
    ];
    
    // Handle recurrence-specific fields
    switch ($recurrenceType) {
        case 'one-off':
            $data['date'] = $_POST['date'] ?? '';
            if (empty($data['date'])) {
                throw new InvalidArgumentException('Due date is required for one-off tasks.');
            }
            break;
            
        case 'annual':
            $month = $_POST['annual_month'] ?? '';
            $day = $_POST['annual_day'] ?? '';
            if (empty($month) || empty($day)) {
                throw new InvalidArgumentException('Month and day are required for annual tasks.');
            }
            $data['annual_date'] = $month . '-' . $day;
            break;
            
        case 'monthly':
            $dayOfMonth = $_POST['day_of_month'] ?? '';
            if (empty($dayOfMonth)) {
                throw new InvalidArgumentException('Day of month is required for monthly tasks.');
            }
            $data['day_of_month'] = (int)$dayOfMonth;
            if ($data['day_of_month'] < 1 || $data['day_of_month'] > 31) {
                throw new InvalidArgumentException('Day of month must be between 1 and 31.');
            }
            break;
            
        case 'periodic':
            $periodicDays = $_POST['periodic_days'] ?? '';
            if (empty($periodicDays)) {
                throw new InvalidArgumentException('Number of days is required for periodic tasks.');
            }
            $data['periodic_days'] = (int)$periodicDays;
            if ($data['periodic_days'] < 1) {
                throw new InvalidArgumentException('Number of days must be at least 1.');
            }
            break;
    }
    
    // Update the task
    TaskManagement::updateTask($ctx, $taskId, $data);
    
    // Success - redirect back to edit page
    $_SESSION['success'] = 'Task updated successfully.';
    header('Location: /task_edit.php?id=' . $taskId);
    exit;
    
} catch (Exception $e) {
    // Error - save form data and redirect back to edit page
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /task_edit.php?id=' . $taskId);
    exit;
}
