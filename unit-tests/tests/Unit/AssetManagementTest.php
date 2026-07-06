<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../www/lib/AssetManagement.php';
require_once __DIR__ . '/../../../www/lib/Files.php';

final class AssetManagementTest extends TestCase
{
    private UserContext $ctx;

    protected function setUp(): void
    {
        test_reset_users();
        pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
        pdo()->exec('TRUNCATE TABLE asset_photos');
        pdo()->exec('TRUNCATE TABLE assets');
        pdo()->exec('TRUNCATE TABLE public_files');
        pdo()->exec('SET FOREIGN_KEY_CHECKS=1');

        pdo()->exec("INSERT INTO users (first_name, last_name, email, password_hash, email_verified_at)
                     VALUES ('Test', 'User', 'test@example.com', 'hash', NOW())");
        $this->ctx = new UserContext((int)pdo()->lastInsertId(), false);
        UserContext::set($this->ctx);
    }

    public function testCreateAndGetAsset(): void
    {
        $id = AssetManagement::createAsset($this->ctx, [
            'name' => 'Water Heater',
            'category' => 'Appliance',
            'description' => 'Basement, installed 2020',
            'purchase_date' => '2020-06-15',
            'purchase_price' => '1,250.50',
            'warranty_info' => '10 year parts',
        ]);

        $asset = AssetManagement::getAsset($id);
        $this->assertSame('Water Heater', $asset['name']);
        $this->assertSame('Appliance', $asset['category']);
        $this->assertSame('2020-06-15', $asset['purchase_date']);
        $this->assertSame('1250.50', $asset['purchase_price']);
    }

    public function testCreateAssetRequiresName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AssetManagement::createAsset($this->ctx, ['name' => '  ']);
    }

    public function testCreateAssetRejectsNegativePrice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AssetManagement::createAsset($this->ctx, ['name' => 'X', 'purchase_price' => '-5']);
    }

    public function testCreateAssetRequiresLogin(): void
    {
        $this->expectException(RuntimeException::class);
        AssetManagement::createAsset(null, ['name' => 'X']);
    }

    public function testUpdateAsset(): void
    {
        $id = AssetManagement::createAsset($this->ctx, ['name' => 'Old Name']);
        AssetManagement::updateAsset($this->ctx, $id, ['name' => 'New Name', 'category' => 'Vehicle']);

        $asset = AssetManagement::getAsset($id);
        $this->assertSame('New Name', $asset['name']);
        $this->assertSame('Vehicle', $asset['category']);
    }

    public function testListAssetsSearchAndPhotoCount(): void
    {
        $a = AssetManagement::createAsset($this->ctx, ['name' => 'Honda Odyssey', 'category' => 'Vehicle']);
        AssetManagement::createAsset($this->ctx, ['name' => 'Roof']);

        $fileId = Files::insertPublicFile('fakebytes', 'image/jpeg', 'car.jpg', $this->ctx->id);
        AssetManagement::addPhoto($this->ctx, $a, $fileId);

        $all = AssetManagement::listAssets();
        $this->assertCount(2, $all);

        $matches = AssetManagement::listAssets('honda');
        $this->assertCount(1, $matches);
        $this->assertSame(1, (int)$matches[0]['photo_count']);
        $this->assertSame($fileId, (int)$matches[0]['first_photo_file_id']);
    }

    public function testDeleteAssetCascadesPhotos(): void
    {
        $id = AssetManagement::createAsset($this->ctx, ['name' => 'Boiler']);
        $fileId = Files::insertPublicFile('fakebytes', 'image/jpeg', 'boiler.jpg', $this->ctx->id);
        AssetManagement::addPhoto($this->ctx, $id, $fileId);

        $this->assertTrue(AssetManagement::deleteAsset($this->ctx, $id));
        $this->assertNull(AssetManagement::getAsset($id));
        $this->assertSame([], AssetManagement::listPhotos($id));
    }

    public function testRemovePhoto(): void
    {
        $id = AssetManagement::createAsset($this->ctx, ['name' => 'HVAC']);
        $fileId = Files::insertPublicFile('fakebytes', 'image/png', 'unit.png', $this->ctx->id);
        $photoId = AssetManagement::addPhoto($this->ctx, $id, $fileId);

        $this->assertTrue(AssetManagement::removePhoto($this->ctx, $photoId));
        $this->assertSame([], AssetManagement::listPhotos($id));
        $this->assertFalse(AssetManagement::removePhoto($this->ctx, $photoId));
    }
}
