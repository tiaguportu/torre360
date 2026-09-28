<?php

namespace Database\Seeders;

use App\Models\AtendimentoSetor;
use Illuminate\Database\Seeder;

class AtendimentoSetoresSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $setores = [
            [
                'nome' => 'Secretaria Escolar',
                'descricao' => 'Dúvidas sobre matrículas, históricos, certidões e documentação acadêmica.',
                'email_notificacao' => 'secretaria@escolatorredemarfim.com.br',
                'ordem' => 1,
                'ativo' => true,
            ],
            [
                'nome' => 'Setor Financeiro',
                'descricao' => 'Assuntos sobre mensalidades, 2ª via de boletos, pagamentos e acordos.',
                'email_notificacao' => 'financeiro@escolatorredemarfim.com.br',
                'ordem' => 2,
                'ativo' => true,
            ],
            [
                'nome' => 'Coordenação Pedagógica',
                'descricao' => 'Orientação educacional, rotina de estudos, desempenho dos alunos e reuniões.',
                'email_notificacao' => 'coordenacao@escolatorredemarfim.com.br',
                'ordem' => 3,
                'ativo' => true,
            ],
            [
                'nome' => 'Ambulatório e Saúde Escolar',
                'descricao' => 'Restrições alimentares, medicamentos de uso contínuo e cuidados médicos.',
                'email_notificacao' => 'saude@escolatorredemarfim.com.br',
                'ordem' => 4,
                'ativo' => true,
            ],
            [
                'nome' => 'Direção e Ouvidoria',
                'descricao' => 'Sugestões, elogios, feedback institucional e assuntos gerais.',
                'email_notificacao' => 'direcao@escolatorredemarfim.com.br',
                'ordem' => 5,
                'ativo' => true,
            ],
        ];

        foreach ($setores as $setor) {
            AtendimentoSetor::updateOrCreate(
                ['nome' => $setor['nome']],
                $setor
            );
        }
    }
}
