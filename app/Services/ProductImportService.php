<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Image;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use League\Csv\Statement;
use Throwable;

class ProductImportService
{
    /**
     * Bulk import products from a CSV file.
     * Upsert by SKU, track invalid & duplicate counts, handle primary image linking.
     */
    public function import(string $csvPath): array
    {
        $summary = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'invalid' => 0,
            'duplicates' => 0,
        ];

        // Load CSV
        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setHeaderOffset(0);

        //Get & trim headers
        $rawHeaders = $csv->getHeader();
        $trimmedHeaders = array_map('trim', $rawHeaders);

        $records = (new Statement())->process($csv);

        // Track SKUs to detect duplicates within same CSV
        $seenSkus = [];
        
        foreach ($records as $row) {
            $summary['total']++;

            $row = array_combine($trimmedHeaders, array_values($row));

            // Trim values too
            $row = array_map('trim', $row);

            // Validate required columns
            if (empty($row['sku']) || empty($row['name'])) {
                $summary['invalid']++;
                continue;
            }

            $sku = trim($row['sku']);
            if (isset($seenSkus[$sku])) {
                $summary['duplicates']++;
                continue;
            }
            $seenSkus[$sku] = true;

            try {
                DB::beginTransaction();

                // Upsert by SKU
                $product = Product::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => $row['name'] ?? '',
                        'description' => $row['description'] ?? null,
                        'price' => isset($row['price']) ? (float)$row['price'] : null,
                    ]
                );

                if ($product->wasRecentlyCreated) {
                    $summary['imported']++;
                } else {
                    $summary['updated']++;
                }

                // If CSV includes an image path (optional)
                if (!empty($row['image_path'])) {
                    $this->attachPrimaryImageFromPath($product, $row['image_path']);
                }

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $summary['invalid']++;
                // Log the error for debugging but don’t stop the import
                \Log::error("Import error on SKU {$sku}: " . $e->getMessage());
            }
        }

        return $summary;
    }

    /**
     * Attach primary image if exists. Idempotent: if same image is already attached, no-op.
     */
    protected function attachPrimaryImageFromPath(Product $product, string $path): void
    {
        $existingImage = Image::where('path', $path)->first();

        if (!$existingImage) {
            // For this task, we assume images have already been uploaded separately
            return;
        }

        if ($product->primary_image_id === $existingImage->id) {
            // already attached — no change
            return;
        }

        $product->update(['primary_image_id' => $existingImage->id]);
    }
}
