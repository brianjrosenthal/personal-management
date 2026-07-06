<?php
declare(strict_types=1);

// Test bootstrap: load the app, then point every pdo() call at a dedicated
// test database (recreated from schema.sql on every run) so tests never touch
// the real database.

require_once __DIR__ . '/../../www/config.php';
require_once __DIR__ . '/../../www/lib/UserManagement.php';
require_once __DIR__ . '/../../www/lib/ActivityLog.php';

const TEST_DB_NAME = 'familyoffice_test';

$server = new PDO(
    'mysql:host=' . DB_HOST . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$server->exec('DROP DATABASE IF EXISTS `' . TEST_DB_NAME . '`');
$server->exec('CREATE DATABASE `' . TEST_DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$testPdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . TEST_DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
$testPdo->exec((string)file_get_contents(__DIR__ . '/../../www/schema.sql'));

set_pdo_for_testing($testPdo);

// Helper for tests: wipe user-related tables back to a clean slate.
function test_reset_users(): void {
    $pdo = pdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('TRUNCATE TABLE activity_log');
    $pdo->exec('TRUNCATE TABLE emails_sent');
    $pdo->exec('TRUNCATE TABLE users');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}
