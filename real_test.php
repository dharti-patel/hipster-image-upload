<?php

use App\Models\Product;
use App\Models\Upload;
use App\Services\ImageProcessingService;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$csvPath = __DIR__ . '/test_products.csv';
$service = new ImageProcessingService();

// Read CSV
$rows = array_map('str_getcsv', file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$header = array_shift($rows);

// Remove BOM and trim spaces from header
$header = array_map(function($h) {
    return trim(str_replace("\xEF\xBB\xBF", '', $h));
}, $header);


if (!$header) {
    die("CSV header not found.\n");
}

foreach ($rows as $row) {
    if (count($row) !== count($header)) {
        continue;
    }

    $data = array_combine($header, $row);

    if (!isset($data['sku'])) {
        continue; 
    }

    $product = Product::updateOrCreate(
        ['sku' => $data['sku']],
        [
            'name' => $data['name'],
            'price' => $data['price'],
            'description' => $data['description']
        ]
    );

    // Attach image if exists
    $productName = trim($data['name']); 
$imageFile = __DIR__ . "/storage/app/public/uploads/{$productName}.jpg";
    echo "Checking image: {$imageFile}\n";
if (!file_exists($imageFile)) {
    echo "Image not found for {$productName}\n";
    continue;
}
    if (file_exists($imageFile)) {
        $checksum = hash_file('sha256', $imageFile);

        $upload = Upload::firstOrCreate(
            ['uuid' => $product->sku],
            [
                'filename' => basename($imageFile),
                'mime' => 'image/jpeg',
                'checksum' => $checksum,
                'status' => 'pending'
            ]
        );

        $service->finalizeUpload($upload, $imageFile, $checksum);
    }
}

echo "CSV import and image upload done!\n";

// List created products
$products = Product::all();
foreach ($products as $p) {
    echo "{$p->sku} - {$p->name} - {$p->price}\n";
    if ($p->image) {
        echo "Image: " . $p->image->path . "\n";
        foreach ($p->image->variants as $size => $path) {
            echo "  Variant {$size}px: {$path}\n";
        }
    }
}
