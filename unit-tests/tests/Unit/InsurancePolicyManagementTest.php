<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../www/lib/InsurancePolicyManagement.php';

final class InsurancePolicyManagementTest extends TestCase
{
    private UserContext $ctx;

    protected function setUp(): void
    {
        test_reset_users();
        pdo()->exec('TRUNCATE TABLE insurance_policies');

        pdo()->exec("INSERT INTO users (first_name, last_name, email, password_hash, email_verified_at)
                     VALUES ('Test', 'User', 'test@example.com', 'hash', NOW())");
        $this->ctx = new UserContext((int)pdo()->lastInsertId(), false);
        UserContext::set($this->ctx);
    }

    public function testCreateAndGetPolicy(): void
    {
        $id = InsurancePolicyManagement::createPolicy($this->ctx, [
            'name' => 'Umbrella 2026',
            'category' => 'Umbrella',
            'insurance_company' => 'Acme Mutual',
            'policy_number' => 'UM-12345',
            'effective_date' => '2026-01-01',
            'expiration_date' => '2027-01-01',
            'notes' => '2M coverage',
        ]);

        $policy = InsurancePolicyManagement::getPolicy($id);
        $this->assertSame('Umbrella 2026', $policy['name']);
        $this->assertSame('Acme Mutual', $policy['insurance_company']);
        $this->assertSame('2027-01-01', $policy['expiration_date']);
    }

    public function testCreatePolicyRequiresName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InsurancePolicyManagement::createPolicy($this->ctx, ['name' => '']);
    }

    public function testExpirationCannotPrecedeEffectiveDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InsurancePolicyManagement::createPolicy($this->ctx, [
            'name' => 'Backwards',
            'effective_date' => '2026-06-01',
            'expiration_date' => '2026-01-01',
        ]);
    }

    public function testUpdatePolicy(): void
    {
        $id = InsurancePolicyManagement::createPolicy($this->ctx, ['name' => 'Auto']);
        InsurancePolicyManagement::updatePolicy($this->ctx, $id, ['name' => 'Auto — Odyssey', 'policy_number' => 'A-99']);

        $policy = InsurancePolicyManagement::getPolicy($id);
        $this->assertSame('Auto — Odyssey', $policy['name']);
        $this->assertSame('A-99', $policy['policy_number']);
    }

    public function testListPoliciesSearch(): void
    {
        InsurancePolicyManagement::createPolicy($this->ctx, ['name' => 'Homeowners', 'insurance_company' => 'Acme']);
        InsurancePolicyManagement::createPolicy($this->ctx, ['name' => 'Life', 'insurance_company' => 'Zenith']);

        $this->assertCount(2, InsurancePolicyManagement::listPolicies());
        $matches = InsurancePolicyManagement::listPolicies('zenith');
        $this->assertCount(1, $matches);
        $this->assertSame('Life', $matches[0]['name']);
    }

    public function testDeletePolicy(): void
    {
        $id = InsurancePolicyManagement::createPolicy($this->ctx, ['name' => 'Temp']);
        $this->assertTrue(InsurancePolicyManagement::deletePolicy($this->ctx, $id));
        $this->assertNull(InsurancePolicyManagement::getPolicy($id));
        $this->assertFalse(InsurancePolicyManagement::deletePolicy($this->ctx, $id));
    }
}
