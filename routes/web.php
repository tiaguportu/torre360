<?php

use App\Http\Controllers\Admin\TemplateCrachaV3Controller;
use App\Http\Controllers\Api\MobileTokenController;
use App\Http\Controllers\BoletimPDFController;
use App\Http\Controllers\Captacao\CaptacaoInteressadoController;
use App\Http\Controllers\Contratos\DownloadContratoController;
use App\Http\Controllers\Contratos\GerarAssinaturaController;
use App\Http\Controllers\Contratos\VisualizarContratoController;
use App\Http\Controllers\Contratos\VisualizarContratoPDFController;
use App\Http\Controllers\Crm\PesquisaVisitaController;
use App\Http\Controllers\Crm\PortalDocumentosCandidatoController;
use App\Http\Controllers\Documentos\VisualizarDocumentoController;
use App\Http\Controllers\DossieIaPdfController;
use App\Http\Controllers\HistoricoEscolarPDFController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\MatriculaOnlineController;
use App\Http\Controllers\QuestionarioRespostaPDFController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ValidarDocumentoController;
use App\Livewire\MatriculaOnline\MatriculaOnlineWizard;
use Illuminate\Support\Facades\Route;

Route::get('/switch-role/{role}', [RoleController::class, 'switch'])->name('switch-role')->middleware('auth');

Route::get('/', [LandingPageController::class, 'index'])->name('home');
Route::post('/solicitar-acesso', [LandingPageController::class, 'store'])->middleware('throttle:5,1')->name('solicitar-acesso');

// Matrícula Externa 100% Self-Service para Novos Alunos
Route::get('/matricular-online', MatriculaOnlineWizard::class)->name('matricular.online');
Route::get('/matricular-online/sucesso/{matricula}', [MatriculaOnlineController::class, 'sucesso'])->name('matricular.online.sucesso');

// Formulário público de captação de interessados
Route::get('/quero-matricular', [CaptacaoInteressadoController::class, 'show'])->name('captacao.interessado.show');
Route::post('/quero-matricular', [CaptacaoInteressadoController::class, 'store'])
    ->middleware('throttle:15,1')
    ->name('captacao.interessado.store');
Route::get('/quero-matricular/obrigado', [CaptacaoInteressadoController::class, 'sucesso'])->name('captacao.interessado.sucesso');

// Convite de matrícula online: link único enviado a um lead já qualificado pelo CRM
Route::get('/quero-matricular/convite/{token}', [CaptacaoInteressadoController::class, 'convite'])
    ->middleware('throttle:15,1')
    ->name('captacao.interessado.convite');
Route::post('/quero-matricular/convite/{token}', [CaptacaoInteressadoController::class, 'confirmarConvite'])
    ->middleware('throttle:15,1')
    ->name('captacao.interessado.convite.confirmar');
Route::get('/quero-matricular/convite/{token}/obrigado', [CaptacaoInteressadoController::class, 'conviteConfirmado'])
    ->name('captacao.interessado.convite.sucesso');

// Validação pública de autenticidade documental (QR Code com proteção contra raspagem)
Route::match(['get', 'post'], '/validar-documento/{codigo?}', ValidarDocumentoController::class)
    ->middleware('throttle:15,1')
    ->name('documentos.validar-autenticidade');

// Pesquisa NPS e Satisfação Pós-Tour Escolar
Route::get('/pesquisa-visita/{token}', [PesquisaVisitaController::class, 'show'])->name('pesquisa-visita.show');
Route::post('/pesquisa-visita/{token}', [PesquisaVisitaController::class, 'store'])
    ->middleware('throttle:15,1')
    ->name('pesquisa-visita.store');
Route::get('/pesquisa-visita/{token}/obrigado', [PesquisaVisitaController::class, 'sucesso'])->name('pesquisa-visita.sucesso');

// Portal de Pré-Admissão & Checklist de Documentos do Candidato
Route::get('/admissao/{token}', [PortalDocumentosCandidatoController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('candidato.documentos.show');
Route::post('/admissao/{token}/enviar', [PortalDocumentosCandidatoController::class, 'upload'])
    ->middleware('throttle:30,1')
    ->name('candidato.documentos.upload');
Route::delete('/admissao/{token}/remover/{documento}', [PortalDocumentosCandidatoController::class, 'remover'])
    ->middleware('throttle:30,1')
    ->name('candidato.documentos.remover');

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

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
    Route::get('/historicos-escolares/{record}/pdf', [HistoricoEscolarPDFController::class, 'stream'])->name('historicos-escolares.pdf');
    Route::get('/historicos-escolares/{record}/download', [HistoricoEscolarPDFController::class, 'download'])->name('historicos-escolares.download');
    Route::get('/questionario-respostas/comparar/pdf', [QuestionarioRespostaPDFController::class, 'download'])->name('questionario-respostas.comparar.pdf');

    // Dossiê Estratégico IA do Lead (PDF)
    Route::get('/crm/interessados/{record}/dossie-pdf', [DossieIaPdfController::class, 'download'])->name('crm.interessados.dossie-pdf');
    Route::get('/crm/interessados/{record}/dossie-pdf/stream', [DossieIaPdfController::class, 'stream'])->name('crm.interessados.dossie-pdf.stream');

    // Editor de Crachás V3 (Moveable)
    Route::get('/admin/template-crachas-v3/{templateCrachaV3}/editor', [TemplateCrachaV3Controller::class, 'editor'])->name('template-crachas-v3.editor');
    Route::post('/admin/template-crachas-v3/{templateCrachaV3}/save', [TemplateCrachaV3Controller::class, 'save'])->name('template-crachas-v3.save');
});

Route::post('/mobile/register-token', [MobileTokenController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('mobile.register-token');
