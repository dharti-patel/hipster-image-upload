<?php

namespace Tests\Unit;

use App\Models\Upload;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class ImageProcessingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_variants_after_checksum_verification()
    {
        Storage::fake('public');

        // Fake upload
        $file = UploadedFile::fake()->create('test.jpg', 100); // just a dummy file
        $checksum = hash_file('sha256', $file->getRealPath());

        $upload = Upload::create([
            'uuid' => 'test-uuid',
            'filename' => 'test.jpg',
            'mime' => 'image/jpeg',
            'size' => $file->getSize(),
            'checksum' => $checksum,
            'status' => 'pending',
        ]);

        // Mock Intervention Image so we don't need GD
        $mock = Mockery::mock('overload:Intervention\Image\ImageManagerStatic');
        $mock->shouldReceive('make')->andReturnSelf();
        $mock->shouldReceive('resize')->andReturnSelf();
        $mock->shouldReceive('toJpeg')->andReturn('dummy content');

        $service = app(ImageProcessingService::class);
        $image = $service->finalizeUpload($upload, $file->getRealPath(), $checksum);

        $this->assertNotNull($image);
        $this->assertEquals('complete', $upload->fresh()->status);

        // Check that variants paths exist in storage
        foreach (['256', '512', '1024'] as $size) {
            Storage::disk('public')->assertExists("images/variants/{$upload->uuid}_{$size}.jpg");
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
