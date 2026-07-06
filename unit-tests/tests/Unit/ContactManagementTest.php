<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../www/lib/ContactManagement.php';

final class ContactManagementTest extends TestCase
{
    private UserContext $ctx;

    protected function setUp(): void
    {
        test_reset_users();
        pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
        pdo()->exec('TRUNCATE TABLE contact_categories');
        pdo()->exec('TRUNCATE TABLE contacts');
        pdo()->exec('SET FOREIGN_KEY_CHECKS=1');

        pdo()->exec("INSERT INTO users (first_name, last_name, email, password_hash, email_verified_at)
                     VALUES ('Test', 'User', 'test@example.com', 'hash', NOW())");
        $this->ctx = new UserContext((int)pdo()->lastInsertId(), false);
        UserContext::set($this->ctx);
    }

    public function testCreateContactWithCategories(): void
    {
        $id = ContactManagement::createContact($this->ctx, [
            'name' => 'Joe the Plumber',
            'contact_type' => 'person',
            'organization' => 'Joe & Sons',
            'phone' => '555-1234',
        ], ['Plumber', 'Emergency Contact']);

        $contact = ContactManagement::getContact($id);
        $this->assertSame('Joe the Plumber', $contact['name']);
        $this->assertSame(['Emergency Contact', 'Plumber'], $contact['categories']);
    }

    public function testInvalidCategoriesAreDropped(): void
    {
        $id = ContactManagement::createContact($this->ctx, ['name' => 'X'], ['Plumber', 'Made Up Role', 'Plumber']);
        $this->assertSame(['Plumber'], ContactManagement::getContact($id)['categories']);
    }

    public function testCreateContactRejectsInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ContactManagement::createContact($this->ctx, ['name' => 'X', 'email' => 'nope']);
    }

    public function testCreateContactRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ContactManagement::createContact($this->ctx, ['name' => 'X', 'contact_type' => 'robot']);
    }

    public function testUpdateContactReplacesCategories(): void
    {
        $id = ContactManagement::createContact($this->ctx, ['name' => 'Dr. Smith'], ['Doctor']);
        ContactManagement::updateContact($this->ctx, $id, ['name' => 'Dr. Smith', 'contact_type' => 'person'], ['Dentist', 'Family Member']);

        $contact = ContactManagement::getContact($id);
        $this->assertSame(['Dentist', 'Family Member'], $contact['categories']);
    }

    public function testListContactsFiltersByCategoryAny(): void
    {
        ContactManagement::createContact($this->ctx, ['name' => 'Dr. Smith'], ['Doctor']);
        ContactManagement::createContact($this->ctx, ['name' => 'Dr. Jones DDS'], ['Dentist']);
        ContactManagement::createContact($this->ctx, ['name' => 'Joe the Plumber'], ['Plumber']);

        // "Doctors" group = Doctor + Dentist
        $doctors = ContactManagement::listContacts('', ContactManagement::CATEGORY_GROUPS['Doctors']);
        $names = array_column($doctors, 'name');
        sort($names);
        $this->assertSame(['Dr. Jones DDS', 'Dr. Smith'], $names);
    }

    public function testListContactsSearchCombinesWithFilter(): void
    {
        ContactManagement::createContact($this->ctx, ['name' => 'Dr. Smith'], ['Doctor']);
        ContactManagement::createContact($this->ctx, ['name' => 'Dr. Jones DDS'], ['Dentist']);

        $matches = ContactManagement::listContacts('jones', ContactManagement::CATEGORY_GROUPS['Doctors']);
        $this->assertCount(1, $matches);
        $this->assertSame('Dr. Jones DDS', $matches[0]['name']);
    }

    public function testDeleteContactRemovesCategories(): void
    {
        $id = ContactManagement::createContact($this->ctx, ['name' => 'Gone Soon'], ['Other']);
        $this->assertTrue(ContactManagement::deleteContact($this->ctx, $id));
        $this->assertNull(ContactManagement::getContact($id));

        $st = pdo()->prepare('SELECT COUNT(*) FROM contact_categories WHERE contact_id = ?');
        $st->execute([$id]);
        $this->assertSame(0, (int)$st->fetchColumn());
    }
}
