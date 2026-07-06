<?php
// Evaluates a mark-complete action (POST from obligations/edit.php or the
// homepage dashboard). Records a completion and advances the schedule.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/ObligationManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /obligations/');
    exit;
}

require_csrf();

$obligationId = (int)($_POST['obligation_id'] ?? 0);
$completedOn = trim((string)($_POST['completed_on'] ?? date('Y-m-d')));
$notes = (string)($_POST['notes'] ?? '');
$return = validate_relative_next_path($_POST['return'] ?? '') ?: '/obligations/';

try {
    $ctx = UserContext::getLoggedInUserContext();
    ObligationManagement::addCompletion($ctx, $obligationId, $completedOn, $notes);
    $obligation = ObligationManagement::getObligation($obligationId);
    $_SESSION['success'] = 'Marked complete.' . ($obligation && $obligation['next_due_on']
        ? ' Next due ' . date('M j, Y', strtotime($obligation['next_due_on'])) . '.'
        : '');
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to mark complete: ' . $e->getMessage();
}

header('Location: ' . $return);
exit;
