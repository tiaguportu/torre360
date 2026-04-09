<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

use App\Http\Controllers\Contratos\VisualizarContratoController;
use App\Http\Controllers\Contratos\GerarAssinaturaController;

Route::middleware(['auth'])->group(function () {
    Route::get('/contratos/{contrato}/visualizar', VisualizarContratoController::class)->name('contratos.visualizar');
    Route::post('/contratos/{contrato}/gerar-assinatura', GerarAssinaturaController::class)->name('contratos.gerar-assinatura');
});
