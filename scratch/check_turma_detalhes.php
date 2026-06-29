<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->instance('request', Request::capture());
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\NotaHabilidade;
use App\Models\Turma;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$ids = [2, 12, 14];
foreach ($ids as $id) {
    $t = Turma::find($id);
    if (! $t) {
        echo "Turma ID $id nao encontrada.".PHP_EOL;

        continue;
    }
    echo "Turma: {$t->nome} (ID: {$t->id}) | Periodo Letivo ID: {$t->periodo_letivo_id}".PHP_EOL;
    $matriculas = $t->matriculas;
    echo '  - Qtd total matriculas: '.$matriculas->count().PHP_EOL;
    foreach ($matriculas as $m) {
        $notasCount = $m->notas()->whereNotNull('valor')->count();
        $habCount = NotaHabilidade::where('matricula_id', $m->id)->count();
        echo "    - Matricula ID: {$m->id} | Situacao: {$m->situacao->value} | Periodo Letivo ID: {$m->periodo_letivo_id} | Notas: {$notasCount} | Habilidades: {$habCount}".PHP_EOL;
    }
}
