<?php
// Evaluates the add-obligation form (POST from obligations/add.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ObligationManagement.php';
require_once __DIR__ . '/form_fields.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /obligations/');
    exit;
}

require_csrf();

try {
    $ctx = UserContext::getLoggedInUserContext();
    $id = ObligationManagement::createObligation($ctx, obligation_data_from_post($_POST));
    $_SESSION['success'] = 'Obligation created. You can link assets, documents, policies, and contacts below.';
    header('Location: /obligations/edit.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /obligations/add.php');
    exit;
}
