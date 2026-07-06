<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';

class InsurancePolicyManagement {
    // Suggested categories (free text — the UI offers these via a datalist)
    public const CATEGORY_SUGGESTIONS = [
        'Homeowners', 'Umbrella', 'Auto', 'Life', 'Disability', 'Health', 'Dental', 'Other',
    ];

    private static function pdo(): PDO {
        return pdo();
    }

    private static function log(string $action, ?int $policyId, array $details = []): void {
        try {
            $ctx = UserContext::getLoggedInUserContext();
            $meta = $details;
            if ($policyId !== null && !array_key_exists('policy_id', $meta)) {
                $meta['policy_id'] = $policyId;
            }
            ActivityLog::log($ctx, $action, $meta);
        } catch (\Throwable $e) {
            // Best-effort logging
        }
    }

    private static function assertLoggedIn(?UserContext $ctx): UserContext {
        if (!$ctx) {
            throw new RuntimeException('Login required');
        }
        return $ctx;
    }

    // Normalize/validate shared form fields.
    private static function normalizeFields(array $data): array {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Policy name is required.');
        }

        $nullable = function (string $key) use ($data): ?string {
            $v = trim((string)($data[$key] ?? ''));
            return $v !== '' ? $v : null;
        };

        $dates = [];
        foreach (['effective_date', 'expiration_date'] as $key) {
            $v = trim((string)($data[$key] ?? ''));
            if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $key)) . ' must be a valid date.');
            }
            $dates[$key] = $v !== '' ? $v : null;
        }
        if ($dates['effective_date'] !== null && $dates['expiration_date'] !== null
            && $dates['expiration_date'] < $dates['effective_date']) {
            throw new InvalidArgumentException('Expiration date cannot be before the effective date.');
        }

        return [
            $name,
            $nullable('category'),
            $nullable('insurance_company'),
            $nullable('policy_number'),
            $dates['effective_date'],
            $dates['expiration_date'],
            $nullable('notes'),
        ];
    }

    public static function createPolicy(?UserContext $ctx, array $data): int {
        $ctx = self::assertLoggedIn($ctx);
        $fields = self::normalizeFields($data);

        $st = self::pdo()->prepare(
            "INSERT INTO insurance_policies (name, category, insurance_company, policy_number, effective_date, expiration_date, notes, created_by_user_id)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $st->execute([...$fields, $ctx->id]);
        $id = (int)self::pdo()->lastInsertId();

        self::log('policy.create', $id, ['name' => $fields[0]]);
        return $id;
    }

    public static function updatePolicy(?UserContext $ctx, int $id, array $data): bool {
        self::assertLoggedIn($ctx);
        $fields = self::normalizeFields($data);

        $st = self::pdo()->prepare(
            "UPDATE insurance_policies SET name=?, category=?, insurance_company=?, policy_number=?, effective_date=?, expiration_date=?, notes=? WHERE id=?"
        );
        $ok = $st->execute([...$fields, $id]);

        if ($ok) {
            self::log('policy.update', $id, ['name' => $fields[0]]);
        }
        return $ok;
    }

    public static function deletePolicy(?UserContext $ctx, int $id): bool {
        self::assertLoggedIn($ctx);
        $policy = self::getPolicy($id);
        if (!$policy) return false;

        $st = self::pdo()->prepare('DELETE FROM insurance_policies WHERE id = ?');
        $ok = $st->execute([$id]);

        if ($ok) {
            self::log('policy.delete', $id, ['name' => $policy['name']]);
        }
        return $ok;
    }

    public static function getPolicy(int $id): ?array {
        $st = self::pdo()->prepare('SELECT * FROM insurance_policies WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function listPolicies(string $search = ''): array {
        $sql = 'SELECT * FROM insurance_policies';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE name LIKE ? OR category LIKE ? OR insurance_company LIKE ? OR policy_number LIKE ?';
            $term = '%' . $search . '%';
            $params = [$term, $term, $term, $term];
        }

        $sql .= ' ORDER BY name';
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }
}
