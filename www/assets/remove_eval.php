<?php
// Evaluates asset deletion (POST from assets/edit.php).
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

try {
    $ctx = UserContext::getLoggedInUserContext();
    if (AssetManagement::deleteAsset($ctx, $id)) {
        $_SESSION['success'] = 'Asset deleted.';
    } else {
        $_SESSION['error'] = 'Asset not found.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to delete asset: ' . $e->getMessage();
}

header('Location: /assets/');
exit;
