<?php
// Evaluates contact deletion (POST from contacts/edit.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ContactManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contacts/');
    exit;
}

require_csrf();

$id = (int)($_POST['id'] ?? 0);

try {
    $ctx = UserContext::getLoggedInUserContext();
    if (ContactManagement::deleteContact($ctx, $id)) {
        $_SESSION['success'] = 'Contact deleted.';
    } else {
        $_SESSION['error'] = 'Contact not found.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to delete contact: ' . $e->getMessage();
}

header('Location: /contacts/');
exit;
