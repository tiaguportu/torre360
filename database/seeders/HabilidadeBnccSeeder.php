<?php

namespace Database\Seeders;

use App\Models\CampoExperiencia;
use App\Models\Habilidade;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HabilidadeBnccSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpar dados anteriores para evitar duplicidade ou lixo
        Schema::disableForeignKeyConstraints();
        Habilidade::truncate();
        CampoExperiencia::truncate();
        DB::table('turma_habilidade')->truncate();
        Schema::enableForeignKeyConstraints();

        // 1. Criar Campos de Experiência (BNCC Educação Infantil)
        $campos = [
            [
                'nome' => 'O eu, o outro e o nós',
                'descricao' => 'É na interação com os pares e com adultos que as crianças vão constituindo um modo próprio de agir, sentir e pensar.',
            ],
            [
                'nome' => 'Corpo, gestos e movimentos',
                'descricao' => 'Com o corpo, as crianças exploram o mundo, o espaço e os objetos, estabelecem relações e se expressam.',
            ],
            [
                'nome' => 'Traços, sons, cores e formas',
                'descricao' => 'Conviver com diferentes manifestações artísticas, culturais e científicas, locais e universais.',
            ],
            [
                'nome' => 'Escuta, fala, pensamento e imaginação',
                'descricao' => 'Desde o nascimento, as crianças participam de situações comunicativas cotidianas com as pessoas com as quais interagem.',
            ],
            [
                'nome' => 'Espaços, tempos, quantidades, relações e transformações',
                'descricao' => 'As crianças vivem inseridas em espaços e tempos de diferentes naturezas, repletos de objetos e fenômenos.',
            ],
        ];

        $camposIds = [];
        foreach ($campos as $campo) {
            $created = CampoExperiencia::updateOrCreate(
                ['nome' => $campo['nome']],
                $campo
            );
            $camposIds[$campo['nome']] = $created->id;
        }

        $publicoBebes = 'Público-alvo: Bebês (zero a 1 ano e 6 meses)';
        $publicoBemPequenas = 'Público-alvo: Crianças bem pequenas (1 ano e 7 meses a 3 anos e 11 meses)';
        $publicoPequenas = 'Público-alvo: Crianças pequenas (4 anos a 5 anos e 11 meses)';

        // 2. Criar Habilidades vinculadas aos Campos
        $habilidades = [
            // O eu, o outro e o nós
            [
                'codigo' => 'EI01EO01',
                'nome' => 'Perceber que suas ações têm efeitos nas outras crianças e nos adultos.',
                'descricao' => $publicoBebes,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['O eu, o outro e o nós'],
            ],
            [
                'codigo' => 'EI02EO03',
                'nome' => 'Compartilhar os objetos e os espaços com crianças da mesma faixa etária e adultos.',
                'descricao' => $publicoBemPequenas,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['O eu, o outro e o nós'],
            ],
            [
                'codigo' => 'EI03EO02',
                'nome' => 'Agir de maneira independente, com confiança em suas capacidades.',
                'descricao' => $publicoPequenas,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['O eu, o outro e o nós'],
            ],

            // Corpo, gestos e movimentos
            [
                'codigo' => 'EI01CG01',
                'nome' => 'Movimentar as partes do corpo para exprimir corporalmente emoções, necessidades e desejos.',
                'descricao' => $publicoBebes,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['Corpo, gestos e movimentos'],
            ],
            [
                'codigo' => 'EI02CG02',
                'nome' => 'Deslocar seu corpo no espaço, orientando-se por noções como em frente, atrás, no alto, embaixo.',
                'descricao' => $publicoBemPequenas,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['Corpo, gestos e movimentos'],
            ],

            // Traços, sons, cores e formas
            [
                'codigo' => 'EI01TS01',
                'nome' => 'Explorar sons produzidos com o próprio corpo e com objetos do cotidiano.',
                'descricao' => $publicoBebes,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['Traços, sons, cores e formas'],
            ],
            [
                'codigo' => 'EI03TS02',
                'nome' => 'Expressar-se livremente por meio de desenho, pintura, colagem, dobradura e escultura.',
                'descricao' => $publicoPequenas,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['Traços, sons, cores e formas'],
            ],

            // Escuta, fala, pensamento e imaginação
            [
                'codigo' => 'EI01EF03',
                'nome' => 'Demonstrar interesse ao ouvir leituras de poemas e a apresentação de músicas.',
                'descricao' => $publicoBebes,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['Escuta, fala, pensamento e imaginação'],
            ],
            [
                'codigo' => 'EI02EF01',
                'nome' => 'Dialogar com crianças e adultos, expressando seus desejos, necessidades, sentimentos e opiniões.',
                'descricao' => $publicoBemPequenas,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['Escuta, fala, pensamento e imaginação'],
            ],

            // Espaços, tempos, quantidades...
            [
                'codigo' => 'EI01ET01',
                'nome' => 'Explorar e descobrir as propriedades de objetos e materiais.',
                'descricao' => $publicoBebes,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['Espaços, tempos, quantidades, relações e transformações'],
            ],
            [
                'codigo' => 'EI03ET07',
                'nome' => 'Relacionar números às suas respectivas quantidades.',
                'descricao' => $publicoPequenas,
                'tipo' => 'BNCC',
                'campo_experiencia_id' => $camposIds['Espaços, tempos, quantidades, relações e transformações'],
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
