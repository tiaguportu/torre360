<?php

namespace Database\Seeders;

use App\Models\Habilidade;
use Illuminate\Database\Seeder;

class HabilidadeBnccSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $habilidades = [
            // Língua Portuguesa (ID 1)
            [
                'codigo' => 'EF01LP01',
                'nome' => 'Reconhecer que textos são lidos e escritos da esquerda para a direita e de cima para baixo.',
                'tipo' => 'BNCC',
                'disciplina_id' => 1,
            ],
            [
                'codigo' => 'EF01LP02',
                'nome' => 'Escrever, espontaneamente ou por ditado, palavras e frases de forma alfabética.',
                'tipo' => 'BNCC',
                'disciplina_id' => 1,
            ],
            [
                'codigo' => 'EF15LP01',
                'nome' => 'Identificar a função social de textos que circulam em campos da vida social cotidiana.',
                'tipo' => 'BNCC',
                'disciplina_id' => 1,
            ],

            // Matemática (ID 2)
            [
                'codigo' => 'EF01MA01',
                'nome' => 'Utilizar números naturais como indicador de quantidade ou de ordem em diferentes situações.',
                'tipo' => 'BNCC',
                'disciplina_id' => 2,
            ],
            [
                'codigo' => 'EF01MA02',
                'nome' => 'Contar de maneira exata ou aproximada, utilizando diferentes estratégias.',
                'tipo' => 'BNCC',
                'disciplina_id' => 2,
            ],

            // Ciências (ID 4)
            [
                'codigo' => 'EF01CI01',
                'nome' => 'Comparar características de diferentes materiais de objetos de uso cotidiano.',
                'tipo' => 'BNCC',
                'disciplina_id' => 4,
            ],
            [
                'codigo' => 'EF01CI02',
                'nome' => 'Localizar, nomear e representar graficamente partes do corpo humano e suas funções.',
                'tipo' => 'BNCC',
                'disciplina_id' => 4,
            ],

            // Artes (ID 9)
            [
                'codigo' => 'EF01AR01',
                'nome' => 'Identificar e apreciar formas distintas das artes visuais tradicionais e contemporâneas.',
                'tipo' => 'BNCC',
                'disciplina_id' => 9,
            ],

            // Educação Física (ID 6)
            [
                'codigo' => 'EF01EF01',
                'nome' => 'Experimentar, fruir e recriar diferentes brincadeiras e jogos da cultura popular.',
                'tipo' => 'BNCC',
                'disciplina_id' => 6,
            ],

            // Inglês (ID 3)
            [
                'codigo' => 'EF06LI01',
                'nome' => 'Interagir em situações de intercâmbio oral, demonstrando iniciativa para utilizar a língua inglesa.',
                'tipo' => 'BNCC',
                'disciplina_id' => 3,
            ],
        ];

        foreach ($habilidades as $habilidade) {
            Habilidade::updateOrCreate(
                ['codigo' => $habilidade['codigo']],
                $habilidade
            );
        }
    }
}
