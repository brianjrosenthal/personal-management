<?php
// AJAX endpoint for the Edit Linked Objects modal (POST from obligations/edit.php).
// Saves the links and returns JSON: {success, html} where html is the refreshed
// linked-objects list fragment, or {success: false, error} on failure.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ObligationManagement.php';
require_once __DIR__ . '/form_fields.php';
require_once __DIR__ . '/linked_objects_fragment.php';
Application::init();
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required.']);
    exit;
}

$token = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Your session expired. Reload the page and try again.']);
    exit;
}

$obligationId = (int)($_POST['obligation_id'] ?? 0);

try {
    $ctx = UserContext::getLoggedInUserContext();
    ObligationManagement::updateLinks($ctx, $obligationId, obligation_links_from_post($_POST));

    echo json_encode([
        'success' => true,
        'html' => render_linked_objects_list(ObligationManagement::getLinkedObjects($obligationId)),
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
