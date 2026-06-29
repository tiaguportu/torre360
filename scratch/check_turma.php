<?php

use App\Models\Turma;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->instance('request', Request::capture());
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$t = Turma::find(1);
echo 'Turma: '.$t->nome.PHP_EOL;
echo 'Qtd matriculas: '.$t->matriculas()->count().PHP_EOL;
$exists = $t->matriculas()->whereHas('notas', fn ($q) => $q->whereNotNull('valor'))->exists();
echo 'Exists notas: '.($exists ? 'TRUE' : 'FALSE').PHP_EOL;
