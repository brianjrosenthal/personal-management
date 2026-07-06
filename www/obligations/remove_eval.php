<?php
// Evaluates obligation deletion (POST from obligations/edit.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ObligationManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /obligations/');
    exit;
}

require_csrf();

$id = (int)($_POST['id'] ?? 0);

try {
    $ctx = UserContext::getLoggedInUserContext();
    if (ObligationManagement::deleteObligation($ctx, $id)) {
        $_SESSION['success'] = 'Obligation deleted.';
    } else {
        $_SESSION['error'] = 'Obligation not found.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to delete obligation: ' . $e->getMessage();
}

header('Location: /obligations/');
exit;
