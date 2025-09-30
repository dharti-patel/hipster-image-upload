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
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('csv_file')->getRealPath();

        try {
            $summary = $this->importService->import($path);

            // Return JSON including products array for image matching
            $products = \App\Models\Product::latest()->take($summary['total'])->get(['sku', 'name']);

            return response()->json([
                'summary' => $summary,
                'products' => $products,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'CSV import failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
