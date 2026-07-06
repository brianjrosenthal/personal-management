<?php
// Evaluates the edit-contact form (POST from contacts/edit.php).
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
if ($id <= 0 || !ContactManagement::getContact($id)) {
    $_SESSION['error'] = 'Contact not found.';
    header('Location: /contacts/');
    exit;
}

try {
    $ctx = UserContext::getLoggedInUserContext();
    ContactManagement::updateContact($ctx, $id, $_POST, (array)($_POST['categories'] ?? []));
    $_SESSION['success'] = 'Contact updated.';
    header('Location: /contacts/edit.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /contacts/edit.php?id=' . $id);
    exit;
}
