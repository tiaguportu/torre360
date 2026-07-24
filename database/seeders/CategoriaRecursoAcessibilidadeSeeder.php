<?php

namespace Database\Seeders;

use App\Models\CategoriaRecursoAcessibilidade;
use Illuminate\Database\Seeder;

class CategoriaRecursoAcessibilidadeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            'Auxílio ledor',
            'Guia-intérprete',
            'Tradutor-intérprete de Libras',
            'Leitura labial',
            'Auxílio transcrição',
            'Material didático em Braille',
            'Prova ampliada (Fonte 18)',
            'Prova em Braille',
            'Prova superampliada (Fonte 24)',
            'CD com áudio para deficiente visual',
            'Prova de Língua Portuguesa como Segunda Língua para surdos e deficientes auditivos',
            'Prova em Vídeo Libras, Tempo adicional',
        ];

        foreach ($categorias as $nome) {
            CategoriaRecursoAcessibilidade::firstOrCreate(['nome' => $nome]);
        }
    }
}
