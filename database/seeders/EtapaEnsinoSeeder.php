<?php

namespace Database\Seeders;

use App\Models\EtapaEnsino;
use App\Models\EtapaEnsinoAgregada;
use Illuminate\Database\Seeder;

class EtapaEnsinoSeeder extends Seeder
{
    public function run(): void
    {
        $dados = [
            [
                'codigo' => '301',
                'nome' => 'Educação Infantil',
                'etapas_ensino' => [
                    ['codigo' => '1', 'nome' => 'Educação infantil - creche (0 a 3 anos)'],
                    ['codigo' => '2', 'nome' => 'Educação infantil - pré-escola (4 e 5 anos)'],
                    ['codigo' => '3', 'nome' => 'Educação infantil - unificada (0 a 5 anos)'],
                ],
            ],
            [
                'codigo' => '302',
                'nome' => 'Ensino Fundamental',
                'etapas_ensino' => [
                    ['codigo' => '14', 'nome' => 'Ensino fundamental de 9 anos - 1º Ano'],
                    ['codigo' => '15', 'nome' => 'Ensino fundamental de 9 anos - 2º Ano'],
                    ['codigo' => '16', 'nome' => 'Ensino fundamental de 9 anos - 3º Ano'],
                    ['codigo' => '17', 'nome' => 'Ensino fundamental de 9 anos - 4º Ano'],
                    ['codigo' => '18', 'nome' => 'Ensino fundamental de 9 anos - 5º Ano'],
                    ['codigo' => '19', 'nome' => 'Ensino fundamental de 9 anos - 6º Ano'],
                    ['codigo' => '20', 'nome' => 'Ensino fundamental de 9 anos - 7º Ano'],
                    ['codigo' => '21', 'nome' => 'Ensino fundamental de 9 anos - 8º Ano'],
                    ['codigo' => '41', 'nome' => 'Ensino fundamental de 9 anos - 9º Ano'],
                ],
            ],
            [
                'codigo' => '303',
                'nome' => 'Multi e correção de fluxo',
                'etapas_ensino' => [
                    ['codigo' => '22', 'nome' => 'Ensino fundamental de 9 anos - multi'],
                    ['codigo' => '23', 'nome' => 'Ensino fundamental de 9 anos - correção de fluxo'],
                    ['codigo' => '56', 'nome' => 'Educação infantil e ensino fundamental - multietapa'],
                ],
            ],
            [
                'codigo' => '304',
                'nome' => 'Ensino Médio',
                'etapas_ensino' => [
                    ['codigo' => '25', 'nome' => 'Ensino médio - 1ª Série'],
                    ['codigo' => '26', 'nome' => 'Ensino médio - 2ª Série'],
                    ['codigo' => '27', 'nome' => 'Ensino médio - 3ª Série'],
                    ['codigo' => '28', 'nome' => 'Ensino médio - 4ª Série'],
                    ['codigo' => '29', 'nome' => 'Ensino médio - não seriada'],
                ],
            ],
            [
                'codigo' => '305',
                'nome' => 'Ensino Médio - Normal/Magistério',
                'etapas_ensino' => [
                    ['codigo' => '35', 'nome' => 'Ensino médio - normal/magistério - 1ª Série'],
                    ['codigo' => '36', 'nome' => 'Ensino médio - normal/magistério - 2ª Série'],
                    ['codigo' => '37', 'nome' => 'Ensino médio - normal/magistério - 3ª Série'],
                    ['codigo' => '38', 'nome' => 'Ensino médio - normal/magistério - 4ª Série'],
                ],
            ],
            [
                'codigo' => '306',
                'nome' => 'Educação de Jovens e Adultos (ensino fundamental, ensino médio e integrada)',
                'etapas_ensino' => [
                    ['codigo' => '69', 'nome' => 'EJA - Ensino fundamental - anos iniciais (1º segmento)'],
                    ['codigo' => '70', 'nome' => 'EJA - Ensino fundamental - anos finais (2º segmento)'],
                    ['codigo' => '72', 'nome' => 'EJA - Ensino fundamental - anos iniciais e anos finais (EJA Multietapas)'],
                    ['codigo' => '71', 'nome' => 'EJA - Ensino médio (3º segmento)'],
                    ['codigo' => '74', 'nome' => 'Curso técnico integrado na modalidade EJA (EJA integrada à educação profissional de nível médio)'],
                    ['codigo' => '73', 'nome' => 'Curso FIC integrado na modalidade EJA - nível fundamental'],
                    ['codigo' => '67', 'nome' => 'Curso FIC integrado na modalidade EJA - nível médio'],
                ],
            ],
            [
                'codigo' => '308',
                'nome' => 'Curso Técnico e Qualificação Profissional (Curso FIC)',
                'etapas_ensino' => [
                    ['codigo' => '39', 'nome' => 'Curso técnico - concomitante'],
                    ['codigo' => '40', 'nome' => 'Curso técnico - subsequente'],
                    ['codigo' => '64', 'nome' => 'Curso técnico misto'],
                    ['codigo' => '68', 'nome' => 'Qualificação profissional (Curso FIC) - concomitante'],
                    ['codigo' => '75', 'nome' => 'Qualificação profissional (Curso FIC) - não vinculada'],
                ],
            ],
        ];

        foreach ($dados as $agregada) {
            $etapaEnsinoAgregada = EtapaEnsinoAgregada::updateOrCreate(
                ['codigo' => $agregada['codigo']],
                ['nome' => $agregada['nome']]
            );

            foreach ($agregada['etapas_ensino'] as $ensino) {
                EtapaEnsino::updateOrCreate(
                    ['codigo' => $ensino['codigo']],
                    [
                        'etapa_ensino_agregada_id' => $etapaEnsinoAgregada->id,
                        'nome' => $ensino['nome'],
                    ]
                );
            }
        }
    }
}
