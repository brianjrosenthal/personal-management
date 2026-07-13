<?php
// Evaluates removal of an update from an obligation's history (POST from
// obligations/edit.php). If the update marked a completion, the completion is
// removed too and the schedule recomputes.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ObligationManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /obligations/');
    exit;
}

require_csrf();

$commentId = (int)($_POST['comment_id'] ?? 0);
$obligationId = (int)($_POST['obligation_id'] ?? 0);

try {
    $ctx = UserContext::getLoggedInUserContext();
    if (ObligationManagement::deleteComment($ctx, $commentId)) {
        $_SESSION['success'] = 'Update removed.';
    } else {
        $_SESSION['error'] = 'Update not found.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to remove update: ' . $e->getMessage();
}

header('Location: ' . ($obligationId > 0 ? '/obligations/edit.php?id=' . $obligationId : '/obligations/'));
exit;
