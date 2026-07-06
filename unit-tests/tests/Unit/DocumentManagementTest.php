<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../www/lib/DocumentManagement.php';

final class DocumentManagementTest extends TestCase
{
    private UserContext $ctx;
    private int $userId;

    protected function setUp(): void
    {
        test_reset_users();
        pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
        pdo()->exec('TRUNCATE TABLE documents');
        pdo()->exec('TRUNCATE TABLE private_files');
        pdo()->exec('SET FOREIGN_KEY_CHECKS=1');

        pdo()->exec("INSERT INTO users (first_name, last_name, email, password_hash, email_verified_at)
                     VALUES ('Test', 'User', 'test@example.com', 'hash', NOW())");
        $this->userId = (int)pdo()->lastInsertId();
        $this->ctx = new UserContext($this->userId, false);
        UserContext::set($this->ctx);
    }

    private function insertFile(string $bytes = '%PDF-1.4 fake'): int
    {
        return Files::insertPrivateFile($bytes, 'application/pdf', 'test.pdf', $this->userId);
    }

    public function testCreateAndGetDocument(): void
    {
        $fileId = $this->insertFile();
        $id = DocumentManagement::createDocument($this->ctx, [
            'title' => 'Homeowners Policy 2026',
            'category' => 'Insurance Policy',
            'description' => 'Current policy',
            'owner_user_id' => $this->userId,
        ], $fileId);

        $doc = DocumentManagement::getDocument($id);
        $this->assertSame('Homeowners Policy 2026', $doc['title']);
        $this->assertSame($fileId, (int)$doc['private_file_id']);
        $this->assertSame('test.pdf', $doc['original_filename']);
        $this->assertSame('Test User', $doc['owner_name']);
    }

    public function testCreateDocumentRequiresTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DocumentManagement::createDocument($this->ctx, ['title' => ''], null);
    }

    public function testReplaceFileDeletesOldBytes(): void
    {
        $oldFile = $this->insertFile('old bytes');
        $id = DocumentManagement::createDocument($this->ctx, ['title' => 'Will'], $oldFile);

        $newFile = $this->insertFile('new bytes');
        DocumentManagement::updateDocument($this->ctx, $id, ['title' => 'Will (updated)'], $newFile);

        $doc = DocumentManagement::getDocument($id);
        $this->assertSame($newFile, (int)$doc['private_file_id']);
        $this->assertNull(Files::getPrivateFileMeta($oldFile), 'Replaced file bytes should be deleted');
        $this->assertNotNull(Files::getPrivateFileMeta($newFile));
    }

    public function testDeleteDocumentDeletesFileBytes(): void
    {
        $fileId = $this->insertFile();
        $id = DocumentManagement::createDocument($this->ctx, ['title' => 'Deed'], $fileId);

        $this->assertTrue(DocumentManagement::deleteDocument($this->ctx, $id));
        $this->assertNull(DocumentManagement::getDocument($id));
        $this->assertNull(Files::getPrivateFileMeta($fileId), 'Vault file bytes should not linger after delete');
    }

    public function testListDocumentsSearch(): void
    {
        DocumentManagement::createDocument($this->ctx, ['title' => 'Passport — Charlie'], $this->insertFile());
        DocumentManagement::createDocument($this->ctx, ['title' => 'Tax Return 2025', 'category' => 'Tax Return'], $this->insertFile());

        $this->assertCount(2, DocumentManagement::listDocuments());
        $matches = DocumentManagement::listDocuments('passport');
        $this->assertCount(1, $matches);
        $this->assertSame('Passport — Charlie', $matches[0]['title']);
    }

    public function testPrivateFileRoundTrip(): void
    {
        $fileId = Files::insertPrivateFile('secret-bytes', 'text/plain', 'note.txt', $this->userId);
        $file = Files::getPrivateFileForDownload($fileId);
        $this->assertSame('secret-bytes', $file['data']);
        $this->assertSame('text/plain', $file['content_type']);
        $this->assertSame(12, (int)$file['byte_length']);
    }
}
