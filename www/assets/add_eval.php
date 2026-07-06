<?php
// Evaluates the add-asset form (POST from assets/add.php).
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/AssetManagement.php';
Application::init();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /assets/');
    exit;
}

require_csrf();

try {
    $ctx = UserContext::getLoggedInUserContext();
    $id = AssetManagement::createAsset($ctx, $_POST);
    $_SESSION['success'] = 'Asset created. You can add photos below.';
    header('Location: /assets/edit.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    header('Location: /assets/add.php');
    exit;
}
