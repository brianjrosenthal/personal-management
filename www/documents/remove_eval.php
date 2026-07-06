<?php
// Evaluates document deletion (POST from documents/edit.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/DocumentManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /documents/');
    exit;
}

require_csrf();

$id = (int)($_POST['id'] ?? 0);

try {
    $ctx = UserContext::getLoggedInUserContext();
    if (DocumentManagement::deleteDocument($ctx, $id)) {
        $_SESSION['success'] = 'Document deleted.';
    } else {
        $_SESSION['error'] = 'Document not found.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to delete document: ' . $e->getMessage();
}

header('Location: /documents/');
exit;
