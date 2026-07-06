<?php
// Evaluates the add-document form (POST from documents/add.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/DocumentManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /documents/');
    exit;
}

require_csrf();

try {
    $ctx = UserContext::getLoggedInUserContext();

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        throw new InvalidArgumentException('A file attachment is required.');
    }
    $fileId = DocumentManagement::storeUploadedFile($ctx, $_FILES['file']);
    DocumentManagement::createDocument($ctx, $_POST, $fileId);

    $_SESSION['success'] = 'Document uploaded.';
    header('Location: /documents/');
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /documents/add.php');
    exit;
}
