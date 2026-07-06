<?php
// Evaluates an asset photo upload (POST from assets/edit.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
require_once __DIR__ . '/../lib/Files.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /assets/');
    exit;
}

require_csrf();

$assetId = (int)($_POST['asset_id'] ?? 0);
if ($assetId <= 0 || !AssetManagement::getAsset($assetId)) {
    $_SESSION['error'] = 'Asset not found.';
    header('Location: /assets/');
    exit;
}

function redirect_back(int $assetId, ?string $error = null, ?string $success = null): void {
    if ($error !== null) $_SESSION['error'] = $error;
    if ($success !== null) $_SESSION['success'] = $success;
    header('Location: /assets/edit.php?id=' . $assetId);
    exit;
}

if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
    redirect_back($assetId, 'No photo was uploaded.');
}

$err = (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err !== UPLOAD_ERR_OK) {
    redirect_back($assetId, 'Photo upload failed (error ' . $err . ').');
}

$size = (int)($_FILES['photo']['size'] ?? 0);
if ($size <= 0) redirect_back($assetId, 'Uploaded photo is empty.');
if ($size > 8 * 1024 * 1024) redirect_back($assetId, 'Photo is too large (max 8MB).');

$tmp = (string)$_FILES['photo']['tmp_name'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($tmp);

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed, true)) {
    redirect_back($assetId, 'Invalid photo type. Please upload JPEG, PNG, WebP, or GIF.');
}
if (@getimagesize($tmp) === false) {
    redirect_back($assetId, 'Invalid image file.');
}

$data = @file_get_contents($tmp);
if ($data === false) {
    redirect_back($assetId, 'Failed to read uploaded photo.');
}

try {
    $ctx = UserContext::getLoggedInUserContext();
    $fileId = Files::insertPublicFile($data, $mime, (string)($_FILES['photo']['name'] ?? 'photo'), $ctx->id);
    AssetManagement::addPhoto($ctx, $assetId, $fileId);
    redirect_back($assetId, null, 'Photo added.');
} catch (Throwable $e) {
    redirect_back($assetId, 'Failed to save photo: ' . $e->getMessage());
}
