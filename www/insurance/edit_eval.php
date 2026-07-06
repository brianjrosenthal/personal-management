<?php
// Evaluates the edit-policy form (POST from insurance/edit.php).
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
if ($id <= 0 || !InsurancePolicyManagement::getPolicy($id)) {
    $_SESSION['error'] = 'Policy not found.';
    header('Location: /insurance/');
    exit;
}

try {
    $ctx = UserContext::getLoggedInUserContext();
    InsurancePolicyManagement::updatePolicy($ctx, $id, $_POST);
    $_SESSION['success'] = 'Policy updated.';
    header('Location: /insurance/edit.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /insurance/edit.php?id=' . $id);
    exit;
}
