<?php

use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OfficeImageController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

// Tags...
Route::get('/tags', TagController::class);

// Offices...
Route::get('/offices', [OfficeController::class, 'index']);
Route::get('/offices/{office}', [OfficeController::class, 'show']);
Route::post('/offices', [OfficeController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
Route::put('/offices/{office}', [OfficeController::class, 'update'])->middleware(['auth:sanctum', 'verified']);
Route::delete('/offices/{office}', [OfficeController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);

Route::post('/offices/{office}/images', [OfficeImageController::class, 'store']);
Route::delete('/offices/{office}/images/{image}', [OfficeImageController::class, 'delete']);
