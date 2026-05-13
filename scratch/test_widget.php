<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Filament\Widgets\QuestionariosPendentes;

try {
    $reflection = new ReflectionClass(QuestionariosPendentes::class);
    echo "Classe carregada de: " . $reflection->getFileName() . "\n";
    $property = $reflection->getProperty('view');
    echo "View: " . ($property->isStatic() ? 'Static' : 'Non-static') . "\n";
    echo "View Type: " . $property->getType() . "\n";
} catch (\Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " na linha " . $e->getLine() . "\n";
}
