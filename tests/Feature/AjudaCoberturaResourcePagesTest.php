<?php

declare(strict_types=1);

namespace Tests\Feature;

use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles;
use Filament\Facades\Filament;
use ReflectionClass;
use Tests\TestCase;

/**
 * Garante que todas as páginas de recursos (Index, Create, Edit, View)
 * implementem a action de cabeçalho 'ajuda' e o método getHelpContent().
 */
class AjudaCoberturaResourcePagesTest extends TestCase
{
    private const EXCECOES = [
        ListRoles::class,
    ];

    private function temAjuda(string $classe): bool
    {
        $ref = new ReflectionClass($classe);
        $fileName = $ref->getFileName();
        if (! $fileName || ! file_exists($fileName)) {
            return true;
        }

        $src = file_get_contents($fileName);

        return str_contains($src, "'ajuda'")
            || str_contains($src, 'ajudaAction(')
            || str_contains($src, 'ajudaCadastro(')
            || str_contains($src, 'getHelpContent');
    }

    public function test_todas_as_paginas_de_recursos_do_painel_admin_possuem_ajuda(): void
    {
        $panel = Filament::getPanel('admin');
        $faltando = [];

        foreach ($panel->getResources() as $resource) {
            foreach ($resource::getPages() as $pageRegistration) {
                $classe = $pageRegistration->getPage();

                // Ignora páginas de pacotes externos como Filament Shield
                if (str_starts_with($classe, 'BezhanSalleh\\') || in_array($classe, self::EXCECOES, true)) {
                    continue;
                }

                if (! $this->temAjuda($classe)) {
                    $faltando[] = $classe;
                }
            }
        }

        $this->assertEmpty(
            $faltando,
            'As seguintes páginas de recursos do painel admin estão sem botão de Ajuda: '.implode(', ', $faltando)
        );
    }
}
