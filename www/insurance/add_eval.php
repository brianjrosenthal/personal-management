<?php
// Evaluates the add-policy form (POST from insurance/add.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/InsurancePolicyManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /insurance/');
    exit;
}

require_csrf();

try {
    $ctx = UserContext::getLoggedInUserContext();
    InsurancePolicyManagement::createPolicy($ctx, $_POST);
    $_SESSION['success'] = 'Policy created.';
    header('Location: /insurance/');
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /insurance/add.php');
    exit;
}
