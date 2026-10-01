<?php

use App\Http\Controllers\Webhooks\AssinafyWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'throttle:60,1']);

Route::match(['get', 'post'], '/webhooks/assinafy', AssinafyWebhookController::class)
    ->middleware('throttle:30,1');
