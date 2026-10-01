<?php

use App\Http\Controllers\Webhooks\AssinafyWebhookController;
use App\Http\Controllers\Webhooks\PagamentoWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::match(['get', 'post'], '/webhooks/assinafy', AssinafyWebhookController::class);
Route::post('/webhooks/pagamento', PagamentoWebhookController::class);
