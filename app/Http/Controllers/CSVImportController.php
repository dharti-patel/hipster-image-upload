<?php

namespace App\Http\Controllers;

use App\Services\ProductImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CSVImportController extends Controller
{
    protected ProductImportService $importService;

    public function __construct(ProductImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Handle CSV file upload and import.
     * POST /api/products/import
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $tempPath = $file->storeAs('temp/csv', uniqid('import_').'.csv');

        $fullPath = Storage::path($tempPath);
        $summary = $this->importService->import($fullPath);

        // Optionally delete after processing
        Storage::delete($tempPath);

        return response()->json([
            'status' => 'success',
            'summary' => $summary,
        ]);
    }
}
