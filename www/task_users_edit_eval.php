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
    $userIdsJson = $_POST['user_ids'] ?? '[]';
    
    if (!$taskId) {
        throw new InvalidArgumentException('Invalid task ID.');
    }
    
    // Verify task exists
    $task = TaskManagement::getTask($taskId);
    if (!$task) {
        throw new InvalidArgumentException('Task not found.');
    }
    
    // Parse user IDs
    $userIds = json_decode($userIdsJson, true);
    if (!is_array($userIds)) {
        $userIds = [];
    }
    
    // Convert to integers
    $userIds = array_map('intval', $userIds);
    
    // Update responsible users
    TaskManagement::setResponsibleUsers($ctx, $taskId, $userIds);
    
    // Get updated list of responsible users
    $responsibleUsers = TaskManagement::getResponsibleUsers($taskId);
    
    // Generate HTML for the updated list
    ob_start();
    if (empty($responsibleUsers)) {
        echo '<p class="text-muted">No users assigned</p>';
    } else {
        echo '<ul>';
        foreach ($responsibleUsers as $user) {
            echo '<li>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul>';
    }
    $html = ob_get_clean();
    
    echo json_encode(['success' => true, 'html' => $html]);
    exit;
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
