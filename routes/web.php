<?php

use App\Http\Controllers\Captacao\CaptacaoInteressadoController;
use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingPageController::class, 'index'])->name('home');
Route::post('/solicitar-acesso', [LandingPageController::class, 'store'])->name('solicitar-acesso');

// Formulário público de captação de interessados
Route::get('/quero-uma-vaga', [CaptacaoInteressadoController::class, 'show'])->name('captacao.interessado.show');
Route::post('/quero-uma-vaga', [CaptacaoInteressadoController::class, 'store'])->name('captacao.interessado.store');
Route::get('/quero-uma-vaga/obrigado', [CaptacaoInteressadoController::class, 'sucesso'])->name('captacao.interessado.sucesso');

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
