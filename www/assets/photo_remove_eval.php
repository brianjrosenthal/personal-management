<?php
// Evaluates asset photo removal (POST from assets/edit.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /assets/');
    exit;
}

require_csrf();

$photoId = (int)($_POST['photo_id'] ?? 0);
$assetId = (int)($_POST['asset_id'] ?? 0);

try {
    $ctx = UserContext::getLoggedInUserContext();
    if (AssetManagement::removePhoto($ctx, $photoId)) {
        $_SESSION['success'] = 'Photo removed.';
    } else {
        $_SESSION['error'] = 'Photo not found.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to remove photo: ' . $e->getMessage();
}

header('Location: ' . ($assetId > 0 ? '/assets/edit.php?id=' . $assetId : '/assets/'));
exit;
