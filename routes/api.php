<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OutboxController;
use Illuminate\Support\Facades\Route;

Route::middleware('atlas.token')->group(function (): void {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/transitions', [OrderController::class, 'transition']);
    Route::get('/outbox', [OutboxController::class, 'index']);
    Route::post('/outbox/{message}/ack', [OutboxController::class, 'acknowledge']);
});
