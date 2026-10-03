<?php

namespace Database\Seeders;

use App\Models\ReguaFollowUp;
use Illuminate\Database\Seeder;

class ReguaFollowUpSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $regras = [
            [
                'nome' => 'Boas-vindas para Novos Leads',
                'gatilho' => 'lead_criado',
                'dias_offset' => 0,
                'canal' => 'email',
                'assunto' => 'Bem-vindo(a) ao {{ESCOLA_NOME}}! Ficamos felizes com seu interesse',
                'mensagem' => '<p>Olá, <strong>{{NOME_RESPONSAVEL}}</strong>!</p><p>Recebemos com muita alegria o seu interesse em conhecer o <strong>{{ESCOLA_NOME}}</strong> para a educação de <strong>{{NOME_ALUNO}}</strong>.</p><p>Nossa equipe pedagógica já está preparando todas as informações sobre nossa proposta e rotina escolar. Que tal agendar uma visita presencial para conhecer nossa estrutura de perto e tirar todas as suas dúvidas com nossos consultores?</p><p>Estamos à disposição para ajudar você nesta escolha tão importante!</p><p>Com carinho,<br><strong>Equipe de Admissões - {{ESCOLA_NOME}}</strong></p>',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 1,
            ],
            [
                'nome' => 'Lembrete de Visita à Escola (1 dia antes)',
                'gatilho' => 'visita_lembrete',
                'dias_offset' => 1,
                'canal' => 'email',
                'assunto' => 'Lembrete: Sua visita ao {{ESCOLA_NOME}} é amanhã!',
                'mensagem' => '<p>Olá, <strong>{{NOME_RESPONSAVEL}}</strong>!</p><p>Estamos ansiosos para receber você e sua família amanhã, <strong>{{DATA_VISITA}} às {{HORARIO_VISITA}}</strong>, para um tour pedagógico em nossa escola.</p><p>📍 <strong>Orientações para sua chegada:</strong><br>- Dirija-se à portaria principal e informe seu nome na recepção.<br>- Temos estacionamento para visitantes no local.<br>- Nosso consultor <strong>{{NOME_CONSULTOR}}</strong> estará aguardando para apresentar todos os espaços da escola e conversar sobre a série <strong>{{SERIE_INTERESSE}}</strong>.</p><p>Caso precise remarcar, por favor responda a este e-mail ou entre em contato conosco.</p><p>Até amanhã!</p>',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 2,
            ],
            [
                'nome' => 'Agradecimento Pós-Visita Realizada (1 dia após)',
                'gatilho' => 'visita_realizada',
                'dias_offset' => 1,
                'canal' => 'email',
                'assunto' => 'Obrigado por nos visitar! O que achou da nossa escola?',
                'mensagem' => '<p>Olá, <strong>{{NOME_RESPONSAVEL}}</strong>!</p><p>Foi um prazer imenso receber você e sua família ontem em nosso colégio!</p><p>Esperamos que tenham gostado de conhecer nosso projeto pedagógico, nossa equipe e o ambiente que preparamos com tanto cuidado para acolher <strong>{{NOME_ALUNO}}</strong>.</p><p>Ficou com alguma dúvida ou gostaria de receber uma simulação das condições de matrícula para a série <strong>{{SERIE_INTERESSE}}</strong>? Nosso consultor <strong>{{NOME_CONSULTOR}}</strong> está à sua inteira disposição.</p><p>Será uma honra fazer parte da formação do seu filho(a)!</p>',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 3,
            ],
            [
                'nome' => 'Reagendamento de Falta na Visita (No-Show)',
                'gatilho' => 'visita_faltou',
                'dias_offset' => 1,
                'canal' => 'email',
                'assunto' => 'Sentimos sua falta no {{ESCOLA_NOME}}! Vamos remarcar?',
                'mensagem' => '<p>Olá, <strong>{{NOME_RESPONSAVEL}}</strong>!</p><p>Notamos que você não pôde comparecer à visita agendada ontem. Sabemos que imprevistos acontecem na rotina!</p><p>Queremos muito apresentar nossa proposta pedagógica e mostrar como o <strong>{{ESCOLA_NOME}}</strong> pode transformar o aprendizado de <strong>{{NOME_ALUNO}}</strong>.</p><p>Quando seria um bom momento para você vir nos conhecer? Responda a este e-mail indicando o melhor dia e horário para agendarmos um novo tour.</p><p>Esperamos por você!</p>',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 4,
            ],
            [
                'nome' => 'Alerta Interno: Lead Estagnado há 7 dias',
                'gatilho' => 'lead_estagnado',
                'dias_offset' => 7,
                'canal' => 'notificacao_sistema',
                'assunto' => 'Atenção: Lead {{NOME_RESPONSAVEL}} sem interação há 7 dias',
                'mensagem' => 'O lead {{NOME_RESPONSAVEL}} (Aluno: {{NOME_ALUNO}}, Série: {{SERIE_INTERESSE}}) está há mais de 7 dias sem qualquer contato registrado. Verifique a temperatura e realize uma nova abordagem para evitar que o lead esfrie.',
                'is_ativo' => true,
                'horario_envio' => '08:00:00',
                'ordem' => 5,
            ],
        ];

        foreach ($regras as $dado) {
            ReguaFollowUp::firstOrCreate(
                ['nome' => $dado['nome']],
                $dado
            );
        }
    }
}
