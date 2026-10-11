<?php

namespace Tests;

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\CachedState;
use Illuminate\Foundation\Testing\Concerns\WithCachedConfig;
use Illuminate\Foundation\Testing\Concerns\WithCachedRoutes;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// Garante o mapeamento PSR-4 para o diretório da aplicação mesmo sob junctions/worktrees no Windows
foreach (spl_autoload_functions() as $loader) {
    if (is_array($loader) && $loader[0] instanceof ClassLoader) {
        $base = dirname(__DIR__);
        $loader[0]->setPsr4('App\\', $base.'/app/');
        $loader[0]->setPsr4('Database\\Factories\\', $base.'/database/factories/');
        $loader[0]->setPsr4('Database\\Seeders\\', $base.'/database/seeders/');
        $loader[0]->setPsr4('Tests\\', $base.'/tests/');
    }
}

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';

        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));

        if (isset(CachedState::$cachedConfig) &&
            isset($this->traitsUsedByTest[WithCachedConfig::class])) {
            $this->markConfigCached($app);
        }

        if (isset(CachedState::$cachedRoutes) &&
            isset($this->traitsUsedByTest[WithCachedRoutes::class])) {
            $app->booting(fn () => $this->markRoutesCached($app));
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
