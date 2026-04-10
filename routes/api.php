<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::match(['get', 'post'], '/webhooks/assinafy', \App\Http\Controllers\Webhooks\AssinafyWebhookController::class);

Route::middleware('auth')->post('/mobile/register-token', [\App\Http\Controllers\Api\MobileTokenController::class, 'store']);
