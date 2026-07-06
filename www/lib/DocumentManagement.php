<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/Files.php';

class DocumentManagement {
    // Suggested categories (free text — the UI offers these via a datalist)
    public const CATEGORY_SUGGESTIONS = [
        'Will', 'Trust', 'Insurance Policy', 'Deed', 'Passport', 'Birth Certificate',
        'Marriage Certificate', 'Tax Return', 'Vaccination Record', 'Vehicle Title', 'Other',
    ];

    // Upload constraints for vault attachments
    public const MAX_FILE_BYTES = 20 * 1024 * 1024; // 20 MB
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv',
    ];

    private static function pdo(): PDO {
        return pdo();
    }

    private static function log(string $action, ?int $documentId, array $details = []): void {
        try {
            $ctx = UserContext::getLoggedInUserContext();
            $meta = $details;
            if ($documentId !== null && !array_key_exists('document_id', $meta)) {
                $meta['document_id'] = $documentId;
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

    // Validate and store an uploaded vault attachment ($_FILES entry).
    // Returns the new private_files id.
    public static function storeUploadedFile(?UserContext $ctx, array $file): int {
        $ctx = self::assertLoggedIn($ctx);

        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('File upload failed (error ' . $err . ').');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            throw new InvalidArgumentException('Uploaded file is empty.');
        }
        if ($size > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException('File is too large (max 20 MB).');
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported file type (' . $mime . '). Allowed: PDF, images, Word, Excel, text.');
        }

        $data = @file_get_contents($tmp);
        if ($data === false) {
            throw new RuntimeException('Failed to read uploaded file.');
        }

        $originalName = (string)($file['name'] ?? 'document');
        return Files::insertPrivateFile($data, $mime, $originalName, $ctx->id);
    }

    // Normalize/validate shared form fields. Returns [title, category, description, owner_user_id]
    private static function normalizeFields(array $data): array {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $category = trim((string)($data['category'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $owner = (int)($data['owner_user_id'] ?? 0);

        return [
            $title,
            $category !== '' ? $category : null,
            $description !== '' ? $description : null,
            $owner > 0 ? $owner : null,
        ];
    }

    public static function createDocument(?UserContext $ctx, array $data, ?int $privateFileId): int {
        $ctx = self::assertLoggedIn($ctx);
        [$title, $category, $description, $ownerUserId] = self::normalizeFields($data);

        $st = self::pdo()->prepare(
            "INSERT INTO documents (title, category, description, owner_user_id, private_file_id, created_by_user_id)
             VALUES (?,?,?,?,?,?)"
        );
        $st->execute([$title, $category, $description, $ownerUserId, $privateFileId, $ctx->id]);
        $id = (int)self::pdo()->lastInsertId();

        self::log('document.create', $id, ['title' => $title]);
        return $id;
    }

    // Update metadata; when $newPrivateFileId is provided the attachment is
    // replaced and the old file's bytes are deleted (sensitive content should
    // not linger).
    public static function updateDocument(?UserContext $ctx, int $id, array $data, ?int $newPrivateFileId = null): bool {
        self::assertLoggedIn($ctx);
        $existing = self::getDocument($id);
        if (!$existing) return false;

        [$title, $category, $description, $ownerUserId] = self::normalizeFields($data);

        if ($newPrivateFileId !== null) {
            $st = self::pdo()->prepare(
                "UPDATE documents SET title=?, category=?, description=?, owner_user_id=?, private_file_id=? WHERE id=?"
            );
            $ok = $st->execute([$title, $category, $description, $ownerUserId, $newPrivateFileId, $id]);
            if ($ok && !empty($existing['private_file_id'])) {
                Files::deletePrivateFile((int)$existing['private_file_id']);
            }
        } else {
            $st = self::pdo()->prepare(
                "UPDATE documents SET title=?, category=?, description=?, owner_user_id=? WHERE id=?"
            );
            $ok = $st->execute([$title, $category, $description, $ownerUserId, $id]);
        }

        if ($ok) {
            self::log('document.update', $id, ['title' => $title, 'file_replaced' => $newPrivateFileId !== null]);
        }
        return $ok;
    }

    public static function deleteDocument(?UserContext $ctx, int $id): bool {
        self::assertLoggedIn($ctx);
        $doc = self::getDocument($id);
        if (!$doc) return false;

        $st = self::pdo()->prepare('DELETE FROM documents WHERE id = ?');
        $ok = $st->execute([$id]);

        if ($ok) {
            if (!empty($doc['private_file_id'])) {
                Files::deletePrivateFile((int)$doc['private_file_id']);
            }
            self::log('document.delete', $id, ['title' => $doc['title']]);
        }
        return $ok;
    }

    // Fetch one document with attachment metadata and owner name (no blob)
    public static function getDocument(int $id): ?array {
        $st = self::pdo()->prepare(
            "SELECT d.*, pf.original_filename, pf.byte_length, pf.content_type,
                    CONCAT(u.first_name, ' ', u.last_name) AS owner_name
             FROM documents d
             LEFT JOIN private_files pf ON pf.id = d.private_file_id
             LEFT JOIN users u ON u.id = d.owner_user_id
             WHERE d.id = ? LIMIT 1"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function listDocuments(string $search = ''): array {
        $sql = "SELECT d.*, pf.original_filename, pf.byte_length,
                       CONCAT(u.first_name, ' ', u.last_name) AS owner_name
                FROM documents d
                LEFT JOIN private_files pf ON pf.id = d.private_file_id
                LEFT JOIN users u ON u.id = d.owner_user_id";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE d.title LIKE ? OR d.category LIKE ? OR d.description LIKE ?";
            $term = '%' . $search . '%';
            $params = [$term, $term, $term];
        }

        $sql .= " ORDER BY d.title";
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }
}
