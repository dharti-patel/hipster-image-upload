<?php

namespace Tests\Unit;

use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use Tests\TestCase;

class ProductUpsertTest extends TestCase
{
    use RefreshDatabase;

    protected string $csvPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->csvPath = storage_path('app/test_products.csv');
    }

    /** @test */
    public function it_imports_valid_products_from_csv()
    {
        // Arrange
        $csv = Writer::createFromPath($this->csvPath, 'w+');
        $csv->insertOne(['sku', 'name', 'price', 'description']);
        $csv->insertAll([
            ['SKU001', 'First Product', '10.00', 'Nice'],
            ['SKU002', 'Second Product', '20.00', 'Cool'],
        ]);

        $service = app(ProductImportService::class);

        // Act
        $summary = $service->import($this->csvPath);

        // Assert
        $this->assertEquals(2, $summary['total']);
        $this->assertEquals(2, $summary['imported']);
        $this->assertDatabaseHas('products', ['sku' => 'SKU001', 'name' => 'First Product']);
        $this->assertDatabaseHas('products', ['sku' => 'SKU002', 'name' => 'Second Product']);
    }

    /** @test */
    public function it_skips_duplicates_and_invalid_rows()
    {
        // Arrange
        $csv = Writer::createFromPath($this->csvPath, 'w+');
        $csv->insertOne(['sku', 'name', 'price', 'description']);
        $csv->insertAll([
            ['SKU001', 'First Product', '10.00', 'Nice'],
            ['SKU001', 'Duplicate Product', '15.00', 'Updated'], // duplicate
            ['', 'Invalid Product', '30.00', 'Missing SKU'],     // invalid
        ]);

        $service = app(ProductImportService::class);

        // Act
        $summary = $service->import($this->csvPath);

        // Assert
        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(1, $summary['imported']);
        $this->assertEquals(1, $summary['duplicates']);
        $this->assertEquals(1, $summary['invalid']);
    }

    /** @test */
    public function it_updates_existing_products_on_reimport()
    {
        // Arrange: create initial product
        $csv = Writer::createFromPath($this->csvPath, 'w+');
        $csv->insertOne(['sku', 'name', 'price', 'description']);
        $csv->insertOne(['SKU001', 'Original Product', '10.00', 'Original']);

        $service = app(ProductImportService::class);
        $service->import($this->csvPath);

        // Act: update product via CSV
        $csv = Writer::createFromPath($this->csvPath, 'w+');
        $csv->insertOne(['sku', 'name', 'price', 'description']);
        $csv->insertOne(['SKU001', 'Updated Product', '12.00', 'Edited']);

        $summary2 = $service->import($this->csvPath);

        // Assert
        $this->assertEquals(1, $summary2['total']);
        $this->assertEquals(0, $summary2['imported']);
        $this->assertEquals(1, $summary2['updated']);

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU001',
            'name' => 'Updated Product',
            'price' => 12.00,
            'description' => 'Edited',
        ]);
    }
}
