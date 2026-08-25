<?php

use App\Http\Controllers\Api\PrintQueueController;
use Illuminate\Support\Facades\Route;

Route::middleware('terminal.token')->prefix('print-jobs')->group(function () {
    Route::get('/', [PrintQueueController::class, 'index']);
    Route::post('/{printJob}/ack', [PrintQueueController::class, 'ack']);
    Route::post('/{printJob}/fail', [PrintQueueController::class, 'fail']);
});
