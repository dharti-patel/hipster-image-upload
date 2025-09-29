<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChunkUploadController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('uploads')->group(function () {
    Route::post('start', [ChunkUploadController::class, 'start']);
    Route::post('chunk', [ChunkUploadController::class, 'uploadChunk']);
    Route::post('finalize', [ChunkUploadController::class, 'finalize']);
});

Route::post('/products/attach-image', function (Request $request) {
    $request->validate([
        'sku' => 'required|string',
        'image_id' => 'required|integer',
    ]);

    $product = \App\Models\Product::where('sku', $request->sku)->firstOrFail();
    $product->primary_image_id = $request->image_id;
    $product->save();

    return response()->json(['status' => 'linked']);
});