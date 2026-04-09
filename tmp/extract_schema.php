<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

$modelFiles = glob(__DIR__.'/../app/Models/*.php');
$documentation = [];

foreach ($modelFiles as $file) {
    try {
        $className = 'App\\Models\\'.basename($file, '.php');
        if (! class_exists($className)) {
            continue;
        }

        $reflection = new ReflectionClass($className);
        if ($reflection->isAbstract()) {
            continue;
        }

        $instance = new $className;
        $table = $instance->getTable();

        $columns = [];
        try {
            if (Schema::hasTable($table)) {
                $columns = Schema::getColumnListing($table);
            } else {
                continue;
            }
        } catch (Exception $e) {
            continue;
        }

        $relationships = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getFileName() !== $reflection->getFileName()) {
                continue;
            }

            $doc = $method->getDocComment();
            $returnType = $method->getReturnType();

            $isRelation = false;
            $relationType = '';

            if ($returnType instanceof ReflectionNamedType) {
                $typeName = $returnType->getName();
                if (str_contains($typeName, 'Relations')) {
                    $isRelation = true;
                    $relationType = basename(str_replace('\\', '/', $typeName));
                }
            } elseif ($doc && (preg_match('/@return\s+.*Relations\\\\(\w+)/', $doc, $m))) {
                $isRelation = true;
                $relationType = $m[1];
            }

            if (! $isRelation) {
                $methodCode = file_get_contents($file);
                // Extracting method body roughly
                $start = $method->getStartLine() - 1;
                $end = $method->getEndLine();
                $lines = explode("\n", $methodCode);
                $body = implode("\n", array_slice($lines, $start, $end - $start));

                if (preg_match('/return\s+\$this->(hasOne|hasMany|belongsTo|belongsToMany|morphTo|morphMany|morphToMany|morphedByMany|hasOneThrough|hasManyThrough)\(/', $body, $matches)) {
                    $isRelation = true;
                    $relationType = $matches[1];
                }
            }

            if ($isRelation) {
                $relationships[] = [
                    'name' => $method->getName(),
                    'type' => $relationType,
                ];
            }
        }

        $documentation[$className] = [
            'table' => $table,
            'columns' => $columns,
            'relationships' => $relationships,
        ];
    } catch (Throwable $t) {
        continue;
    }
}

echo json_encode($documentation, JSON_PRETTY_PRINT);
