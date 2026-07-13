<?php
// Evaluates an "Update Obligation" submission (POST from obligations/edit.php,
// or the homepage dashboard's quick Mark Complete): a comment and/or an
// attachment, optionally marking the obligation complete.
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
$comment = trim((string)($_POST['comment'] ?? ''));
$completed = !empty($_POST['completed']);
$completedOn = trim((string)($_POST['completed_on'] ?? '')) ?: date('Y-m-d');
$return = validate_relative_next_path($_POST['return'] ?? '') ?: '/obligations/';

$hasAttachment = isset($_FILES['attachment']) && is_array($_FILES['attachment'])
    && (int)($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

try {
    $ctx = UserContext::getLoggedInUserContext();

    if (!ObligationManagement::getObligation($obligationId)) {
        throw new InvalidArgumentException('Obligation not found.');
    }
    if ($comment === '' && !$hasAttachment && !$completed) {
        throw new InvalidArgumentException('Add a comment, attach a file, or check "Mark complete".');
    }

    $privateFileId = $hasAttachment
        ? ObligationManagement::storeUploadedAttachment($ctx, $_FILES['attachment'])
        : null;

    $completionId = null;
    if ($completed) {
        $completionId = ObligationManagement::addCompletion($ctx, $obligationId, $completedOn);
    }

    // A bare quick-complete (no comment, no file) needs no comment row — the
    // completion itself is the history entry.
    if ($comment !== '' || $privateFileId !== null) {
        ObligationManagement::addComment($ctx, $obligationId, $comment, $privateFileId, $completionId);
    }

    if ($completed) {
        $obligation = ObligationManagement::getObligation($obligationId);
        $_SESSION['success'] = 'Marked complete.' . ($obligation && $obligation['next_due_on']
            ? ' Next due ' . date('M j, Y', strtotime($obligation['next_due_on'])) . '.'
            : '');
    } else {
        $_SESSION['success'] = 'Update added.';
    }
} catch (Throwable $e) {
    $_SESSION['error'] = 'Failed to add update: ' . $e->getMessage();
}

header('Location: ' . $return);
exit;
