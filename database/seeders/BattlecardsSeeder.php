<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cidade;
use App\Models\Concorrente;
use App\Models\Objecao;
use Illuminate\Database\Seeder;

class BattlecardsSeeder extends Seeder
{
    public function run(): void
    {
        $cidade = Cidade::first();

        // 1. Concorrentes Padrão
        if (Concorrente::count() === 0) {
            Concorrente::create([
                'nome' => 'Colégio Dom Bosco Regional',
                'sigla' => 'CDB',
                'cidade_id' => $cidade?->id,
                'bairro' => 'Centro',
                'faixa_preco' => 'mais_barato',
                'mensalidade_estimada' => 1100.00,
                'proposta_pedagogica' => 'Ensino tradicional focado em apostilas padronizadas e disciplina rígida.',
                'pontos_fortes' => [
                    'Mensalidade mais barata que a nossa',
                    'Tradição antiga de marca na cidade',
                    'Estrutura com quadra poliesportiva coberta',
                ],
                'pontos_fracos' => [
                    'Turmas muito cheias (35 a 40 alunos por sala)',
                    'Pouco acolhimento emocional e sem preceptoria individual',
                    'Comunicação burocrática e lenta com os pais',
                    'Pouco estímulo ao pensamento crítico e liderança',
                ],
                'diferenciais_nossos' => "1. Turmas reduzidas com atenção personalizada para cada criança.\n2. Programa de preceptoria e formação humana com valores sólidos.\n3. Canal direto e humanizado com a coordenação sem burocracia.",
                'estrategia_abordagem' => 'Reconheça a tradição do Dom Bosco, mas pergunte aos pais: "Vocês preferem que seu filho seja mais um número numa sala com 40 alunos ou tenha um plano de desenvolvimento individualizado com os professores acompanhando de perto?"',
                'is_ativo' => true,
            ]);

            Concorrente::create([
                'nome' => 'Instituto Educacional Vanguarda',
                'sigla' => 'IEV',
                'cidade_id' => $cidade?->id,
                'bairro' => 'Jardins',
                'faixa_preco' => 'mais_caro',
                'mensalidade_estimada' => 2400.00,
                'proposta_pedagogica' => 'Metodologia bilíngue com forte apelo tecnológico e instalações modernas.',
                'pontos_fortes' => [
                    'Marketing digital agressivo e moderno',
                    'Prédio espelhado e estrutura visual imponente',
                    'Parceria com programa internacional de idiomas',
                ],
                'pontos_fracos' => [
                    'Custo proibitivo para a maioria das famílias',
                    'Cobrança excessiva de materiais e taxas extras durante o ano',
                    'Rotatividade alta de professores e monitores de inglês',
                    'Ambiente competitivo que gera ansiedade em crianças pequenas',
                ],
                'diferenciais_nossos' => "1. Excelente custo-benefício com investimento justo e sem taxas ocultas.\n2. Corpo docente estável, capacitado e afetivo com baixa rotatividade.\n3. Clima escolar acolhedor onde a criança tem prazer em aprender.",
                'estrategia_abordagem' => 'Destaque que inovação pedagógica real depende de professores dedicados e acolhimento, e não apenas de tablets e fachadas de vidro. Traga a transparência financeira da nossa escola.',
                'is_ativo' => true,
            ]);
        }

        // 2. Matriz de Objeções Padrão
        if (Objecao::count() === 0) {
            $objecoes = [
                [
                    'titulo' => 'Achei a mensalidade um pouco acima do nosso orçamento',
                    'categoria' => 'preco',
                    'descricao' => 'Os pais elogiam a visita e a escola, mas dizem que o valor mensal está acima do planejado para este ano.',
                    'dicas_postura' => 'Não dê desconto imediatamente. Entenda se a questão é fluxo de caixa ou se eles ainda não perceberam o valor e a segurança que a escola entrega.',
                    'resposta_sugerida' => 'Eu entendo perfeitamente, o investimento na educação dos nossos filhos é uma das decisões mais importantes do orçamento familiar. Mas me permita perguntar: além do valor da mensalidade, o que mais pesou no coração de vocês quando conheceram o projeto pedagógico e o acolhimento que daremos ao seu filho?',
                    'pergunta_virada' => 'Se o valor couber em uma condição especial parcelada, vocês sentem que a nossa escola é o lugar ideal onde gostariam de ver o desenvolvimento do seu filho?',
                    'ordem' => 1,
                ],
                [
                    'titulo' => 'A escola fica um pouco distante da nossa rotina de trânsito',
                    'categoria' => 'distancia',
                    'descricao' => 'A família relata preocupação com logística diária, trânsito ou horário de buscar a criança.',
                    'dicas_postura' => 'Mostre empatia com a rotina e apresente soluções de vans credenciadas, horários flexíveis e período integral.',
                    'resposta_sugerida' => 'A rotina das famílias hoje realmente é muito intensa. Nós temos convênio com rotas de vans escolares de total confiança que atendem exatamente o seu bairro. Além disso, a segurança e a paz de espírito de saber que seu filho está no melhor ambiente pedagógico compensam alguns minutos de percurso.',
                    'pergunta_virada' => 'Faz sentido conectarmos vocês hoje mesmo com um dos motoristas de van credenciados para vocês avaliarem a facilidade do trajeto?',
                    'ordem' => 2,
                ],
                [
                    'titulo' => 'Tenho medo de uma metodologia muito tradicional / ou muito flexível',
                    'categoria' => 'pedagogico',
                    'descricao' => 'Dúvidas sobre o método de ensino, se a criança vai sofrer com pressão ou se vai ficar solta sem base para o futuro.',
                    'dicas_postura' => 'Apresente o equilíbrio entre formação socioemocional e solidez acadêmica.',
                    'resposta_sugerida' => 'Essa preocupação é legítima e mostra o quanto vocês prezam pelo futuro dele. Aqui nós não trabalhamos com extremos: nem um ensino conteudista frio que adoece a criança, nem a falta de rotina. Nós desenvolvemos autonomia, leitura sólida, raciocínio lógico e valores éticos no ritmo de cada aluno.',
                    'pergunta_virada' => 'Gostariam de assistir uma aula modelo ou conversar com nossa coordenadora pedagógica sobre como planejamos a adaptação dele?',
                    'ordem' => 3,
                ],
                [
                    'titulo' => 'Achei o espaço esportivo ou a quadra menor que a do concorrente',
                    'categoria' => 'estrutura',
                    'descricao' => 'Comparação direta com megaestruturas físicas ou colégios com muitos metros quadrados.',
                    'dicas_postura' => 'Reenquadre espaço físico com supervisão, segurança e integração real.',
                    'resposta_sugerida' => 'O espaço de outros colégios pode ser amplo, mas aqui nós priorizamos segurança máxima e visibilidade total da criança em todos os momentos do recreio. Nossos ambientes são projetados com propósito pedagógico: cada metro quadrado é utilizado para convivência sadia, sem cantos cegos e com monitoramento contínuo.',
                    'pergunta_virada' => 'Para a rotina do seu filho, o que traz mais tranquilidade: um pátio gigantesco ou um ambiente seguro onde todos os educadores conhecem seu filho pelo nome?',
                    'ordem' => 4,
                ],
            ];

            foreach ($objecoes as $obj) {
                Objecao::create($obj + ['is_ativo' => true]);
            }
        }
    }
}
