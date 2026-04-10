<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

use App\Http\Controllers\Contratos\DownloadContratoController;
use App\Http\Controllers\Contratos\GerarAssinaturaController;
use App\Http\Controllers\Contratos\VisualizarContratoController;

Route::middleware(['auth'])->group(function () {
    Route::get('/contratos/{contrato}/visualizar', VisualizarContratoController::class)->name('contratos.visualizar');
    Route::get('/contratos/{contrato}/download', DownloadContratoController::class)->name('contratos.download');
    Route::post('/contratos/{contrato}/gerar-assinatura', GerarAssinaturaController::class)->name('contratos.gerar-assinatura');
});
