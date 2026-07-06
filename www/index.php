<?php
require_once __DIR__ . '/partials.php';
Application::init();
require_login();

$me = current_user();
$announcement = Settings::announcement();
$siteTitle = Settings::siteTitle();

header_html('Home');
?>

<?php if (trim($announcement) !== ''): ?>
  <p class="announcement"><?=h($announcement)?></p>
<?php endif; ?>

<div class="card">
  <h2>Welcome to <?= h($siteTitle) ?></h2>
  <p>Hello, <?= h($me['first_name'] ?? '') ?>!</p>
  <p class="small">This page will become the recurring obligations dashboard.</p>
</div>

<?php footer_html(); ?>
