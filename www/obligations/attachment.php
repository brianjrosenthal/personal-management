<?php
// Download an obligation update's attachment. LOGIN REQUIRED — attachments
// (receipts etc.) are private files and are never served from the disk cache.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ObligationManagement.php';
require_once __DIR__ . '/../lib/Files.php';
Application::init();
require_login();

$commentId = (int)($_GET['comment_id'] ?? 0);
$comment = $commentId > 0 ? ObligationManagement::getComment($commentId) : null;
if (!$comment || empty($comment['private_file_id'])) {
    http_response_code(404);
    exit('Attachment not found');
}

$file = Files::getPrivateFileForDownload((int)$comment['private_file_id']);
if (!$file) {
    http_response_code(404);
    exit('File not found');
}

$filename = (string)($file['original_filename'] ?? 'attachment');
// Sanitize for the Content-Disposition header
$safeName = preg_replace('/[^\w.\- ]+/u', '_', $filename) ?: 'attachment';

header('Content-Type: ' . ($file['content_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . strlen((string)$file['data']));
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

echo $file['data'];
exit;
