<?php
// Insurance Policies — list and search.
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/InsurancePolicyManagement.php';
Application::init();
require_login();

$search = trim($_GET['q'] ?? '');
$policies = InsurancePolicyManagement::listPolicies($search);

// One-shot flash from eval pages
$msg = $_SESSION['success'] ?? null;
$err = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

header_html('Insurance Policies');
?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
  <h2>Insurance Policies</h2>
  <a class="button primary" href="/insurance/add.php">Add Policy</a>
</div>

<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="get" data-auto-submit>
    <label>Search
      <input type="text" name="q" value="<?=h($search)?>" placeholder="Name, category, company, or policy number">
    </label>
  </form>
</div>

<?php if (empty($policies)): ?>
  <p class="small"><?= $search !== '' ? 'No policies match your search.' : 'No insurance policies yet. Track homeowners, auto, umbrella, life, and disability coverage here.' ?></p>
<?php else: ?>
  <div class="card">
    <table class="list">
      <thead>
        <tr>
          <th>Policy</th>
          <th>Category</th>
          <th>Company</th>
          <th>Policy #</th>
          <th>Expires</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($policies as $policy): ?>
          <?php
            $expires = $policy['expiration_date'] ?? null;
            $expired = $expires !== null && $expires < date('Y-m-d');
            $expiringSoon = $expires !== null && !$expired && $expires <= date('Y-m-d', strtotime('+30 days'));
          ?>
          <tr>
            <td><a href="/insurance/edit.php?id=<?= (int)$policy['id'] ?>"><?=h($policy['name'])?></a></td>
            <td><?=h($policy['category'] ?? '')?></td>
            <td><?=h($policy['insurance_company'] ?? '')?></td>
            <td><?=h($policy['policy_number'] ?? '')?></td>
            <td>
              <?php if ($expires): ?>
                <?=h(date('M j, Y', strtotime($expires)))?>
                <?php if ($expired): ?>
                  <span class="status-pending" style="color:#b91c1c;">Expired</span>
                <?php elseif ($expiringSoon): ?>
                  <span class="status-pending">Expiring soon</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td class="small" style="text-align:right;">
              <a class="button small" href="/insurance/edit.php?id=<?= (int)$policy['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php footer_html(); ?>
