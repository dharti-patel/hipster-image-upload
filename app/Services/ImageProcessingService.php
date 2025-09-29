<?php

namespace App\Services;

use App\Models\Image as ImageModel;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImageProcessingService
{
    /**
     * Finalize a chunked upload: verify checksum, assemble chunks, generate variants.
     * This version mocks variants so it works on Windows without Intervention Image.
     */
    public function finalizeUpload(Upload $upload, string $tempPath, string $checksum): ?ImageModel
    {
        if ($upload->status === 'complete') {
            // already processed
            return $upload->image;
        }

        if ($checksum !== $upload->checksum) {
            throw new \RuntimeException("Checksum mismatch for upload {$upload->uuid}");
        }

        // Save original file
        $originalPath = "images/original/{$upload->uuid}_" . $upload->filename;
        Storage::disk('public')->put($originalPath, file_get_contents($tempPath));

        // Mock variant generation (just copies original)
        $variants = $this->generateVariantsMock($tempPath, $upload->uuid);

        try {
            $image = ImageModel::create([
                'upload_id' => $upload->id,
                'path' => $originalPath,
                'mime' => $upload->mime,
                'variants' => $variants,
            ]);

            $upload->update(['status' => 'complete']);
            return $image;
        } catch (Throwable $e) {
            \Log::error('Image processing failed: ' . $e->getMessage());
            $upload->update(['status' => 'failed']);
            return null;
        }
    }

    /**
     * Mock variant generation: simply copies the original file to simulate resized images.
     */
    protected function generateVariantsMock(string $tempPath, string $uuid): array
    {
        $sizes = [256, 512, 1024];
        $variants = [];

        foreach ($sizes as $size) {
            $variantPath = "images/variants/{$uuid}_{$size}.jpg";

            // Just copy original file (mock)
            Storage::disk('public')->put($variantPath, file_get_contents($tempPath));

            $variants[(string)$size] = $variantPath;
        }

        return $variants;
    }
}
