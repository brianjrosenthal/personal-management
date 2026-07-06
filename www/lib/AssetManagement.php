<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';

class AssetManagement {
    // Suggested categories (free text — the UI offers these via a datalist)
    public const CATEGORY_SUGGESTIONS = [
        'House', 'Roof', 'Boiler', 'HVAC', 'Water Heater', 'Vehicle',
        'Jewelry', 'Appliance', 'Electronics', 'Furniture', 'Other',
    ];

    private static function pdo(): PDO {
        return pdo();
    }

    private static function log(string $action, ?int $assetId, array $details = []): void {
        try {
            $ctx = UserContext::getLoggedInUserContext();
            $meta = $details;
            if ($assetId !== null && !array_key_exists('asset_id', $meta)) {
                $meta['asset_id'] = $assetId;
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

    // Normalize/validate form fields shared by create and update.
    // Returns [name, category, description, purchase_date, purchase_price, warranty_info]
    private static function normalizeFields(array $data): array {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }

        $category = trim((string)($data['category'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $warranty = trim((string)($data['warranty_info'] ?? ''));

        $purchaseDate = trim((string)($data['purchase_date'] ?? ''));
        if ($purchaseDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
            throw new InvalidArgumentException('Purchase date must be a valid date.');
        }

        $priceRaw = trim((string)($data['purchase_price'] ?? ''));
        $price = null;
        if ($priceRaw !== '') {
            $priceRaw = str_replace([',', '$'], '', $priceRaw);
            if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
                throw new InvalidArgumentException('Purchase price must be a non-negative number.');
            }
            $price = number_format((float)$priceRaw, 2, '.', '');
        }

        return [
            $name,
            $category !== '' ? $category : null,
            $description !== '' ? $description : null,
            $purchaseDate !== '' ? $purchaseDate : null,
            $price,
            $warranty !== '' ? $warranty : null,
        ];
    }

    public static function createAsset(?UserContext $ctx, array $data): int {
        $ctx = self::assertLoggedIn($ctx);
        [$name, $category, $description, $purchaseDate, $price, $warranty] = self::normalizeFields($data);

        $st = self::pdo()->prepare(
            "INSERT INTO assets (name, category, description, purchase_date, purchase_price, warranty_info, created_by_user_id)
             VALUES (?,?,?,?,?,?,?)"
        );
        $st->execute([$name, $category, $description, $purchaseDate, $price, $warranty, $ctx->id]);
        $id = (int)self::pdo()->lastInsertId();

        self::log('asset.create', $id, ['name' => $name]);
        return $id;
    }

    public static function updateAsset(?UserContext $ctx, int $id, array $data): bool {
        self::assertLoggedIn($ctx);
        [$name, $category, $description, $purchaseDate, $price, $warranty] = self::normalizeFields($data);

        $st = self::pdo()->prepare(
            "UPDATE assets SET name=?, category=?, description=?, purchase_date=?, purchase_price=?, warranty_info=? WHERE id=?"
        );
        $ok = $st->execute([$name, $category, $description, $purchaseDate, $price, $warranty, $id]);

        if ($ok) {
            self::log('asset.update', $id, ['name' => $name]);
        }
        return $ok;
    }

    public static function deleteAsset(?UserContext $ctx, int $id): bool {
        self::assertLoggedIn($ctx);
        $asset = self::getAsset($id);
        if (!$asset) return false;

        // asset_photos rows cascade; the public_files bytes stay (immutable, orphaned)
        $st = self::pdo()->prepare('DELETE FROM assets WHERE id = ?');
        $ok = $st->execute([$id]);

        if ($ok) {
            self::log('asset.delete', $id, ['name' => $asset['name']]);
        }
        return $ok;
    }

    public static function getAsset(int $id): ?array {
        $st = self::pdo()->prepare('SELECT * FROM assets WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    // List assets with photo counts and one representative photo file id
    public static function listAssets(string $search = ''): array {
        $sql = "SELECT a.*,
                       COUNT(ap.id) AS photo_count,
                       MIN(ap.public_file_id) AS first_photo_file_id
                FROM assets a
                LEFT JOIN asset_photos ap ON ap.asset_id = a.id";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE a.name LIKE ? OR a.category LIKE ? OR a.description LIKE ?";
            $term = '%' . $search . '%';
            $params = [$term, $term, $term];
        }

        $sql .= " GROUP BY a.id ORDER BY a.name";
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    // ===== Photos =====

    public static function addPhoto(?UserContext $ctx, int $assetId, int $publicFileId): int {
        self::assertLoggedIn($ctx);
        if (!self::getAsset($assetId)) {
            throw new InvalidArgumentException('Asset not found.');
        }

        $st = self::pdo()->prepare('INSERT INTO asset_photos (asset_id, public_file_id) VALUES (?, ?)');
        $st->execute([$assetId, $publicFileId]);
        $id = (int)self::pdo()->lastInsertId();

        self::log('asset.photo_add', $assetId, ['public_file_id' => $publicFileId]);
        return $id;
    }

    public static function removePhoto(?UserContext $ctx, int $photoId): bool {
        self::assertLoggedIn($ctx);

        $st = self::pdo()->prepare('SELECT asset_id FROM asset_photos WHERE id = ? LIMIT 1');
        $st->execute([$photoId]);
        $row = $st->fetch();
        if (!$row) return false;

        $del = self::pdo()->prepare('DELETE FROM asset_photos WHERE id = ?');
        $ok = $del->execute([$photoId]);

        if ($ok) {
            self::log('asset.photo_remove', (int)$row['asset_id'], ['asset_photo_id' => $photoId]);
        }
        return $ok;
    }

    public static function listPhotos(int $assetId): array {
        $st = self::pdo()->prepare('SELECT id, public_file_id, created_at FROM asset_photos WHERE asset_id = ? ORDER BY id');
        $st->execute([$assetId]);
        return $st->fetchAll();
    }
}
