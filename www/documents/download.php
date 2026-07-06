<?php
// Download a vault document's attachment. LOGIN REQUIRED — vault files are
// private (unlike public_files) and are never served from the disk cache.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/DocumentManagement.php';
require_once __DIR__ . '/../lib/Files.php';
Application::init();
require_login();

$docId = (int)($_GET['id'] ?? 0);
$doc = $docId > 0 ? DocumentManagement::getDocument($docId) : null;
if (!$doc || empty($doc['private_file_id'])) {
    http_response_code(404);
    exit('Document not found');
}

$file = Files::getPrivateFileForDownload((int)$doc['private_file_id']);
if (!$file) {
    http_response_code(404);
    exit('File not found');
}

$filename = (string)($file['original_filename'] ?? 'document');
// Sanitize for the Content-Disposition header
$safeName = preg_replace('/[^\w.\- ]+/u', '_', $filename) ?: 'document';

header('Content-Type: ' . ($file['content_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . strlen((string)$file['data']));
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

echo $file['data'];
exit;
