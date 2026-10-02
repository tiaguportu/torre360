<?php

namespace Tests\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Liga o modo estrito de lazy loading durante o teste: qualquer relação carregada sob demanda
 * (N+1) lança LazyLoadingViolationException. O padrão é restaurado ao final de cada teste.
 *
 * Uso: adicione `use ProibeLazyLoading;` à classe de teste (o Laravel chama os métodos
 * setUpProibeLazyLoading/tearDownProibeLazyLoading automaticamente).
 */
trait ProibeLazyLoading
{
    protected function setUpProibeLazyLoading(): void
    {
        Model::preventLazyLoading(true);
    }

    protected function tearDownProibeLazyLoading(): void
    {
        Model::preventLazyLoading(false);
    }
}
