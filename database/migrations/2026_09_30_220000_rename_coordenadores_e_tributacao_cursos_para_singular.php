<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige um rename que nunca chegou a rodar em produção: a migration original
 * (2026_03_27_222005_refactor_tables_to_singular_and_compliance.php) teve esses
 * dois renames adicionados depois de já ter sido marcada como executada em
 * ambientes antigos, então nunca surtiu efeito lá — mas roda normalmente em
 * qualquer ambiente novo (testes, instalação do zero), que já fica com o nome
 * singular. Esta migration nova e idempotente alinha ambientes antigos ao
 * mesmo estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'coordenadores' => 'coordenador',
            'tributacao_cursos' => 'tributacao_curso',
        ];

        foreach ($renames as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
    }

    public function down(): void
    {
        $renames = [
            'coordenador' => 'coordenadores',
            'tributacao_curso' => 'tributacao_cursos',
        ];

        foreach ($renames as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
    }
};
