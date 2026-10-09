<?php

use App\Http\Controllers\Api\CspReportController;
use App\Http\Controllers\Webhooks\AssinafyWebhookController;
use App\Http\Controllers\Webhooks\PagamentoWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'throttle:60,1']);

Route::match(['get', 'post'], '/webhooks/assinafy', AssinafyWebhookController::class)
    ->middleware('throttle:30,1');
Route::post('/webhooks/pagamento', PagamentoWebhookController::class)
    ->middleware('throttle:30,1');

// Relatos de violação da Content-Security-Policy (modo report-only). Enviados pelo navegador, sem sessão.
Route::post('/csp-report', CspReportController::class)
    ->middleware('throttle:30,1');
