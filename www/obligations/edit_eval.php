<?php
// Evaluates the edit-obligation form (POST from obligations/edit.php).
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

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0 || !ObligationManagement::getObligation($id)) {
    $_SESSION['error'] = 'Obligation not found.';
    header('Location: /obligations/');
    exit;
}

try {
    $ctx = UserContext::getLoggedInUserContext();
    // Links are managed separately via the Edit Linked Objects modal
    // (obligations/links_eval.php); the main form leaves them untouched.
    ObligationManagement::updateObligation($ctx, $id, obligation_data_from_post($_POST));
    $_SESSION['success'] = 'Obligation updated.';
    header('Location: /obligations/edit.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /obligations/edit.php?id=' . $id);
    exit;
}
