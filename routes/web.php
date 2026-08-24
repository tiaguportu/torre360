<?php

use App\Http\Controllers\Captacao\CaptacaoInteressadoController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/switch-role/{role}', [RoleController::class, 'switch'])->name('switch-role')->middleware('auth');

Route::get('/', [LandingPageController::class, 'index'])->name('home');
Route::post('/solicitar-acesso', [LandingPageController::class, 'store'])->middleware('throttle:5,1')->name('solicitar-acesso');

// Formulário público de captação de interessados
Route::get('/quero-matricular', [CaptacaoInteressadoController::class, 'show'])->name('captacao.interessado.show');
Route::post('/quero-matricular', [CaptacaoInteressadoController::class, 'store'])->name('captacao.interessado.store');
Route::get('/quero-matricular/obrigado', [CaptacaoInteressadoController::class, 'sucesso'])->name('captacao.interessado.sucesso');

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

use App\Http\Controllers\Admin\TemplateCrachaV3Controller;
use App\Http\Controllers\Api\MobileTokenController;
use App\Http\Controllers\BoletimPDFController;
use App\Http\Controllers\Contratos\DownloadContratoController;
use App\Http\Controllers\Contratos\GerarAssinaturaController;
use App\Http\Controllers\Contratos\VisualizarContratoController;
use App\Http\Controllers\Contratos\VisualizarContratoPDFController;
use App\Http\Controllers\Documentos\VisualizarDocumentoController;
use App\Http\Controllers\QuestionarioRespostaPDFController;

// Rota de visualização de documentos privados (autenticação tratada no controller para evitar 403 do middleware)
Route::get('/visualizar-documento/{path}', VisualizarDocumentoController::class)
    ->where('path', '.*')
    ->name('documentos.visualizar');

Route::middleware(['auth'])->group(function () {
    Route::get('/contratos/{contrato}/visualizar', VisualizarContratoController::class)->name('contratos.visualizar');
    Route::get('/contratos/{contrato}/pdf', VisualizarContratoPDFController::class)->name('contratos.pdf');
    Route::get('/contratos/{contrato}/download', DownloadContratoController::class)->name('contratos.download');
    Route::post('/contratos/{contrato}/gerar-assinatura', GerarAssinaturaController::class)->name('contratos.gerar-assinatura');

    Route::get('/matriculas/{record}/boletim/download', [BoletimPDFController::class, 'download'])->name('matriculas.boletim.download');
    Route::get('/turmas/boletins/download', [BoletimPDFController::class, 'downloadTurmas'])->name('turmas.boletins.download');
    Route::get('/questionario-respostas/comparar/pdf', [QuestionarioRespostaPDFController::class, 'download'])->name('questionario-respostas.comparar.pdf');

    // Editor de Crachás V3 (Moveable)
    Route::get('/admin/template-crachas-v3/{templateCrachaV3}/editor', [TemplateCrachaV3Controller::class, 'editor'])->name('template-crachas-v3.editor');
    Route::post('/admin/template-crachas-v3/{templateCrachaV3}/save', [TemplateCrachaV3Controller::class, 'save'])->name('template-crachas-v3.save');
});

Route::post('/mobile/register-token', [MobileTokenController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('mobile.register-token');
