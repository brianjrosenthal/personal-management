<?php
// Evaluates the edit-asset form (POST from assets/edit.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /assets/');
    exit;
}

require_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0 || !AssetManagement::getAsset($id)) {
    $_SESSION['error'] = 'Asset not found.';
    header('Location: /assets/');
    exit;
}

try {
    $ctx = UserContext::getLoggedInUserContext();
    AssetManagement::updateAsset($ctx, $id, $_POST);
    $_SESSION['success'] = 'Asset updated.';
    header('Location: /assets/edit.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /assets/edit.php?id=' . $id);
    exit;
}
