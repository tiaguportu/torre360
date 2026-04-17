<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\LandingPageController;

Route::get('/', [LandingPageController::class, 'index'])->name('home');
Route::post('/solicitar-acesso', [LandingPageController::class, 'store'])->name('solicitar-acesso');

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

use App\Http\Controllers\Api\MobileTokenController;
use App\Http\Controllers\Contratos\DownloadContratoController;
use App\Http\Controllers\Contratos\GerarAssinaturaController;
use App\Http\Controllers\Contratos\VisualizarContratoController;

use App\Http\Controllers\Documentos\VisualizarDocumentoController;

Route::middleware(['auth'])->group(function () {
    Route::get('/contratos/{contrato}/visualizar', VisualizarContratoController::class)->name('contratos.visualizar');
    Route::get('/contratos/{contrato}/download', DownloadContratoController::class)->name('contratos.download');
    Route::post('/contratos/{contrato}/gerar-assinatura', GerarAssinaturaController::class)->name('contratos.gerar-assinatura');
    
    // Rota de visualização de documentos privados
    Route::get('/visualizar-documento/{path}', VisualizarDocumentoController::class)
        ->where('path', '.*')
        ->name('documentos.visualizar');
});

Route::post('/mobile/register-token', [MobileTokenController::class, 'store'])->name('mobile.register-token');
