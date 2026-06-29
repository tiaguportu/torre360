<?php

use App\Models\Nota;
use App\Models\NotaHabilidade;
use App\Models\Turma;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->instance('request', Request::capture());
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$turmaId = 1; // BER
$turma = Turma::find($turmaId);
if (! $turma) {
    echo 'Turma BER (ID 1) nao encontrada.'.PHP_EOL;
    exit;
}

echo 'Turma: '.$turma->nome.PHP_EOL;

$matriculasIds = $turma->matriculas()->pluck('id')->toArray();
$qtdNotas = Nota::whereIn('matricula_id', $matriculasIds)->whereNotNull('valor')->count();
echo 'Qtd Notas de Disciplinas: '.$qtdNotas.PHP_EOL;

$qtdHabilidades = NotaHabilidade::whereIn('matricula_id', $matriculasIds)->count();
echo 'Qtd Notas de Habilidades: '.$qtdHabilidades.PHP_EOL;
