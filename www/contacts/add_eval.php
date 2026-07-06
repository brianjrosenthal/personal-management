<?php
// Evaluates the add-contact form (POST from contacts/add.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ContactManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contacts/');
    exit;
}

require_csrf();

try {
    $ctx = UserContext::getLoggedInUserContext();
    ContactManagement::createContact($ctx, $_POST, (array)($_POST['categories'] ?? []));
    $_SESSION['success'] = 'Contact created.';
    header('Location: /contacts/');
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /contacts/add.php');
    exit;
}
