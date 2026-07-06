<?php
// Evaluates removal of a completion-history entry (POST from obligations/edit.php).
// The obligation's schedule is recomputed from the remaining history.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ObligationManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /obligations/');
    exit;
}

require_csrf();

$completionId = (int)($_POST['completion_id'] ?? 0);
$obligationId = (int)($_POST['obligation_id'] ?? 0);

try {
    $ctx = UserContext::getLoggedInUserContext();
    if (ObligationManagement::deleteCompletion($ctx, $completionId)) {
        $_SESSION['success'] = 'History entry removed and schedule recomputed.';
    } else {
        $_SESSION['error'] = 'History entry not found.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to remove history entry: ' . $e->getMessage();
}

header('Location: ' . ($obligationId > 0 ? '/obligations/edit.php?id=' . $obligationId : '/obligations/'));
exit;
