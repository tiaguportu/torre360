<?php

namespace Database\Seeders;

use App\Models\CategoriaTranstornoAprendizagem;
use Illuminate\Database\Seeder;

class CategoriaTranstornoAprendizagemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            'Discalculia ou outro transtorno da matemática e raciocínio lógico',
            'Disgrafia, Disortografia ou outro transtorno da escrita e ortografia',
            'Dislalia ou outro transtorno da linguagem e comunicação',
            'Dislexia',
            'Transtorno do Déficit de Atenção com Hiperatividade (TDAH)',
            'Transtorno do Processamento Auditivo Central (TPAC)',
        ];

        foreach ($categorias as $nome) {
            CategoriaTranstornoAprendizagem::firstOrCreate(['nome' => $nome]);
        }
    }
}
