<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Services\ImageProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkUploadController extends Controller
{
    protected ImageProcessingService $imageService;

    public function __construct(ImageProcessingService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Initiate an upload session.
     * POST /api/uploads/start
     */
    public function start(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'mime' => 'nullable|string',
            'size' => 'required|integer',
            'checksum' => 'required|string', // sha256 from client
        ]);

        $uuid = (string) Str::uuid();

        $upload = Upload::create([
            'uuid' => $uuid,
            'filename' => $request->filename,
            'mime' => $request->mime,
            'size' => $request->size,
            'checksum' => $request->checksum,
            'status' => 'pending',
        ]);

        return response()->json([
            'uuid' => $uuid,
            'status' => 'started',
        ]);
    }

    /**
     * Receive a chunk.
     * POST /api/uploads/chunk
     */
    public function uploadChunk(Request $request)
    {
        $request->validate([
            'uuid' => 'required|string',
            'chunk' => 'required|file',
            'index' => 'required|integer', // chunk index
        ]);

        $upload = Upload::where('uuid', $request->uuid)->firstOrFail();

        // Chunk file stored in temp folder based on uuid
        $chunkPath = "temp/chunks/{$upload->uuid}_{$request->index}";
        Storage::disk('local')->put($chunkPath, file_get_contents($request->file('chunk')->getRealPath()));

        return response()->json(['status' => 'chunk_received']);
    }

    /**
     * Finalize upload: assemble chunks, verify checksum, generate variants.
     * POST /api/uploads/finalize
     */
    public function finalize(Request $request)
    {
        $request->validate([
            'uuid' => 'required|string',
        ]);

        $upload = Upload::where('uuid', $request->uuid)->firstOrFail();

        // Assemble chunks in correct order into one temp file
        $assembledPath = storage_path("app/temp/assembled_{$upload->uuid}");
        $chunkFiles = collect(Storage::disk('local')->files('temp/chunks'))
            ->filter(fn($f) => str_starts_with(basename($f), "{$upload->uuid}_"))
            ->sort(function ($a, $b) {
                return intval(explode('_', basename($a))[1]) <=> intval(explode('_', basename($b))[1]);
            });

        $assembled = fopen($assembledPath, 'w');

        foreach ($chunkFiles as $file) {
            $chunk = Storage::disk('local')->get($file);
            fwrite($assembled, $chunk);
        }

        fclose($assembled);

        // Pass to ImageProcessingService for checksum + variant generation
        $image = $this->imageService->finalizeUpload($upload, $assembledPath, $upload->checksum);

        // Cleanup chunk files
        foreach ($chunkFiles as $file) {
            Storage::disk('local')->delete($file);
        }
        Storage::delete('temp/assembled_'.$upload->uuid);

        if (!$image) {
            return response()->json(['status' => 'failed'], 422);
        }

        return response()->json([
            'status' => 'completed',
            'image_id' => $image->id,
            'variants' => $image->variants,
        ]);
    }
}
