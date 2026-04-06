<?php

require 'vendor/autoload.php';
use Illuminate\Support\Str;

echo 'Pais -> '.Str::plural('pais').PHP_EOL;
echo 'Estado -> '.Str::plural('estado').PHP_EOL;
echo 'Cidade -> '.Str::plural('cidade').PHP_EOL;
echo 'Matricula -> '.Str::plural('matricula').PHP_EOL;
echo 'Curso -> '.Str::plural('curso').PHP_EOL;
echo 'Titulo -> '.Str::plural('titulo').PHP_EOL;
echo 'Contrato -> '.Str::plural('contrato').PHP_EOL;
