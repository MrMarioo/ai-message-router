<?php

use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('messages', [MessageController::class, 'store'])->name('api.messages.store');
});
