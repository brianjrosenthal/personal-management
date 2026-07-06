<?php
// Evaluates policy deletion (POST from insurance/edit.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/InsurancePolicyManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /insurance/');
    exit;
}

require_csrf();

$id = (int)($_POST['id'] ?? 0);

try {
    $ctx = UserContext::getLoggedInUserContext();
    if (InsurancePolicyManagement::deletePolicy($ctx, $id)) {
        $_SESSION['success'] = 'Policy deleted.';
    } else {
        $_SESSION['error'] = 'Policy not found.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to delete policy: ' . $e->getMessage();
}

header('Location: /insurance/');
exit;
