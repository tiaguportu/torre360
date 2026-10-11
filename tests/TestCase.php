<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// Garante o mapeamento PSR-4 para o diretório da aplicação mesmo sob junctions/worktrees no Windows
foreach (spl_autoload_functions() as $loader) {
    if (is_array($loader) && $loader[0] instanceof \Composer\Autoload\ClassLoader) {
        $base = dirname(__DIR__);
        $loader[0]->setPsr4('App\\', $base . '/app/');
        $loader[0]->setPsr4('Database\\Factories\\', $base . '/database/factories/');
        $loader[0]->setPsr4('Database\\Seeders\\', $base . '/database/seeders/');
        $loader[0]->setPsr4('Tests\\', $base . '/tests/');
    }
}

abstract class TestCase extends BaseTestCase
{
    //
}
