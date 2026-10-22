<?php

declare(strict_types=1);

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Garante que todas as tabelas de recursos (*Table.php) e abas internas (*RelationManager.php)
 * configurem o método ->stackedOnMobile(), assegurando exibição em cards responsivos em telas pequenas.
 */
class FilamentTablesStackedOnMobileTest extends TestCase
{
    public function test_todas_as_classes_de_tabela_e_relation_managers_possuem_stacked_on_mobile(): void
    {
        $dir = app_path('Filament');
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        $semStacked = [];

        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $content = file_get_contents($path);

            if (str_ends_with($path, 'Table.php')) {
                if (! str_contains($content, 'stackedOnMobile')) {
                    $semStacked[] = str_replace(base_path().'/', '', $path);
                }
            } elseif (str_ends_with($path, 'RelationManager.php')) {
                if (str_contains($content, 'public function table(') || str_contains($content, 'public static function table(')) {
                    if (! str_contains($content, 'stackedOnMobile')) {
                        $semStacked[] = str_replace(base_path().'/', '', $path);
                    }
                }
            }
        }

        $this->assertEmpty(
            $semStacked,
            'As seguintes tabelas e RelationManagers do Filament não possuem ->stackedOnMobile(): '.implode(', ', $semStacked)
        );
    }
}
