<?php
// Evaluates the edit-document form (POST from documents/edit.php).
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
if ($id <= 0 || !DocumentManagement::getDocument($id)) {
    $_SESSION['error'] = 'Document not found.';
    header('Location: /documents/');
    exit;
}

try {
    $ctx = UserContext::getLoggedInUserContext();

    // Optional replacement file
    $newFileId = null;
    if (isset($_FILES['file']) && is_array($_FILES['file'])
        && (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $newFileId = DocumentManagement::storeUploadedFile($ctx, $_FILES['file']);
    }

    DocumentManagement::updateDocument($ctx, $id, $_POST, $newFileId);
    $_SESSION['success'] = 'Document updated.';
    header('Location: /documents/edit.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /documents/edit.php?id=' . $id);
    exit;
}
