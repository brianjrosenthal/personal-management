<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';

class ContactManagement {
    // Fixed category/role list from the spec (a contact may hold several)
    public const CATEGORIES = [
        'Family Member', 'Doctor', 'Dentist', 'Attorney', 'Accountant',
        'Financial Advisor', 'Insurance Agent', 'Contractor', 'Plumber',
        'Electrician', 'HVAC', 'Gardener', 'School', 'Emergency Contact', 'Other',
    ];

    // Quick-filter groups shown in the directory UI
    public const CATEGORY_GROUPS = [
        'Family' => ['Family Member'],
        'Doctors' => ['Doctor', 'Dentist'],
        'Legal / Financial' => ['Attorney', 'Accountant', 'Financial Advisor'],
        'Insurance' => ['Insurance Agent'],
        'Home Services' => ['Contractor', 'Plumber', 'Electrician', 'HVAC', 'Gardener'],
        'Emergency' => ['Emergency Contact'],
    ];

    private static function pdo(): PDO {
        return pdo();
    }

    private static function log(string $action, ?int $contactId, array $details = []): void {
        try {
            $ctx = UserContext::getLoggedInUserContext();
            $meta = $details;
            if ($contactId !== null && !array_key_exists('contact_id', $meta)) {
                $meta['contact_id'] = $contactId;
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
            throw new InvalidArgumentException('Name is required.');
        }

        $type = (string)($data['contact_type'] ?? 'person');
        if (!in_array($type, ['person', 'organization'], true)) {
            throw new InvalidArgumentException('Invalid contact type.');
        }

        $email = trim((string)($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email is invalid.');
        }

        $nullable = function (string $key) use ($data): ?string {
            $v = trim((string)($data[$key] ?? ''));
            return $v !== '' ? $v : null;
        };

        return [
            $name,
            $type,
            $nullable('organization'),
            $nullable('job_title'),
            $nullable('phone'),
            $email !== '' ? $email : null,
            $nullable('website'),
            $nullable('address'),
            $nullable('notes'),
        ];
    }

    private static function normalizeCategories(array $categories): array {
        $out = [];
        foreach ($categories as $c) {
            $c = trim((string)$c);
            if ($c !== '' && in_array($c, self::CATEGORIES, true) && !in_array($c, $out, true)) {
                $out[] = $c;
            }
        }
        return $out;
    }

    public static function createContact(?UserContext $ctx, array $data, array $categories = []): int {
        $ctx = self::assertLoggedIn($ctx);
        $fields = self::normalizeFields($data);
        $categories = self::normalizeCategories($categories);

        $st = self::pdo()->prepare(
            "INSERT INTO contacts (name, contact_type, organization, job_title, phone, email, website, address, notes, created_by_user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([...$fields, $ctx->id]);
        $id = (int)self::pdo()->lastInsertId();

        self::replaceCategories($id, $categories);
        self::log('contact.create', $id, ['name' => $fields[0], 'categories' => $categories]);
        return $id;
    }

    public static function updateContact(?UserContext $ctx, int $id, array $data, array $categories = []): bool {
        self::assertLoggedIn($ctx);
        $fields = self::normalizeFields($data);
        $categories = self::normalizeCategories($categories);

        $st = self::pdo()->prepare(
            "UPDATE contacts SET name=?, contact_type=?, organization=?, job_title=?, phone=?, email=?, website=?, address=?, notes=? WHERE id=?"
        );
        $ok = $st->execute([...$fields, $id]);

        if ($ok) {
            self::replaceCategories($id, $categories);
            self::log('contact.update', $id, ['name' => $fields[0], 'categories' => $categories]);
        }
        return $ok;
    }

    public static function deleteContact(?UserContext $ctx, int $id): bool {
        self::assertLoggedIn($ctx);
        $contact = self::getContact($id);
        if (!$contact) return false;

        $st = self::pdo()->prepare('DELETE FROM contacts WHERE id = ?');
        $ok = $st->execute([$id]); // contact_categories rows cascade

        if ($ok) {
            self::log('contact.delete', $id, ['name' => $contact['name']]);
        }
        return $ok;
    }

    // Fetch one contact with its categories as an array under 'categories'
    public static function getContact(int $id): ?array {
        $st = self::pdo()->prepare('SELECT * FROM contacts WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return null;

        $cs = self::pdo()->prepare('SELECT category FROM contact_categories WHERE contact_id = ? ORDER BY category');
        $cs->execute([$id]);
        $row['categories'] = array_column($cs->fetchAll(), 'category');
        return $row;
    }

    // List contacts, optionally filtered by search text and/or a set of
    // categories (a contact matches if it holds ANY of the given categories).
    // Each row includes 'categories' as a comma-separated string.
    public static function listContacts(string $search = '', array $categories = []): array {
        $categories = self::normalizeCategories($categories);

        $sql = "SELECT c.*, GROUP_CONCAT(cc.category ORDER BY cc.category SEPARATOR ', ') AS categories
                FROM contacts c
                LEFT JOIN contact_categories cc ON cc.contact_id = c.id";
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(c.name LIKE ? OR c.organization LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.notes LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term, $term);
        }

        if (!empty($categories)) {
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            $where[] = "c.id IN (SELECT contact_id FROM contact_categories WHERE category IN ($placeholders))";
            array_push($params, ...$categories);
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY c.id ORDER BY c.name';

        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    private static function replaceCategories(int $contactId, array $categories): void {
        $del = self::pdo()->prepare('DELETE FROM contact_categories WHERE contact_id = ?');
        $del->execute([$contactId]);

        if (!empty($categories)) {
            $ins = self::pdo()->prepare('INSERT INTO contact_categories (contact_id, category) VALUES (?, ?)');
            foreach ($categories as $c) {
                $ins->execute([$contactId, $c]);
            }
        }
    }
}
