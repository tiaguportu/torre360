<?php

namespace Database\Seeders;

use App\Models\CategoriaNecessidadeEducacaoEspecial;
use Illuminate\Database\Seeder;

class CategoriaNecessidadeEducacaoEspecialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            'Baixa visão',
            'Cegueira',
            'Visão monocular',
            'Deficiência auditiva',
            'Surdez',
            'Surdocegueira',
            'Deficiência física',
            'Deficiência intelectual',
            'Deficiência múltipla',
            'Transtorno do espectro autista',
            'Altas habilidades ou superdotação',
        ];

        foreach ($categorias as $nome) {
            CategoriaNecessidadeEducacaoEspecial::firstOrCreate(['nome' => $nome]);
        }
    }
}
