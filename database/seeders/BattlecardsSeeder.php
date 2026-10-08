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

        $cidadeRio = Cidade::where('nome', 'Rio de Janeiro')->first() ?? Cidade::first();

        // Limpa eventuais concorrentes de teste antigos que não sejam os reais
        Concorrente::whereIn('nome', ['Colégio Dom Bosco Regional', 'Instituto Educacional Vanguarda'])->delete();

        // 1. Concorrentes Reais da Escola Torre de Marfim (Jardim Guanabara / Ilha do Governador)
        $concorrentes = [
            [
                'nome' => 'Creche Escola Cambalhota',
                'sigla' => 'Cambalhota',
                'cidade_id' => $cidadeRio?->id,
                'bairro' => 'Jardim Guanabara',
                'faixa_preco' => 'equivalente',
                'mensalidade_estimada' => 1650.00,
                'proposta_pedagogica' => 'Educação Infantil e Creche com abordagem socioafetiva e lúdica focada na primeira infância.',
                'pontos_fortes' => [
                    'Acolhimento afetivo e clima familiar tradicional no Jardim Guanabara',
                    'Localização residencial tranquila na Rua Desembargador Martinho Garcez',
                    'Foco exclusivo na Educação Infantil e primeiras fases',
                    'Ambiente aconchegante para bebês e maternal',
                ],
                'pontos_fracos' => [
                    'Estrutura física compacta (casa adaptada com áreas ao ar livre reduzidas)',
                    'Pouca ênfase em metodologia estruturada de pré-alfabetização fônica',
                    'Sem continuidade de ensino após a educação infantil',
                    'Menos recursos de tecnologia e projetos científicos na rotina',
                ],
                'diferenciais_nossos' => "• Método Fônico Estruturado: A Torre de Marfim prepara a criança para a pré-alfabetização com base científica sólida e comprovada.\n• Estrutura Planejada com Segurança: Ambientes desenhados especificamente para estimulação psicomotora e cognitiva.\n• Preceptoria e Valores: Acompanhamento individualizado que alinha a formação de virtudes e hábitos com a família.\n• Transição Segura: Base consistente para o ingresso tranquilo nas fases seguintes.",
                'estrategia_abordagem' => 'Elogie o carinho da Cambalhota e apresente nosso método: "A Cambalhota é uma escola muito acolhedora para os primeiros passos. Aqui na Torre de Marfim, nós mantemos todo esse afeto de mãe, mas agregamos um projeto pedagógico consistente com o método fônico, para que seu filho desenvolva autonomia e amor pelas letras e números desde cedo. Como vocês veem essa preparação para os próximos anos?"',
                'observacoes' => "Endereço: Rua Desembargador Martinho Garcez, 21 - Jardim Guanabara, Ilha do Governador, Rio de Janeiro - RJ.\nTelefone: (21) 2462-5361.\nSegmento: Creche e Educação Infantil.",
                'is_ativo' => true,
            ],
            [
                'nome' => 'Colégio COC Jardim Guanabara',
                'sigla' => 'COC Ilha',
                'cidade_id' => $cidadeRio?->id,
                'bairro' => 'Jardim Guanabara',
                'faixa_preco' => 'equivalente',
                'mensalidade_estimada' => 1950.00,
                'proposta_pedagogica' => 'Sistema de Ensino COC Pearson, conteudista e preparatório com plataforma digital.',
                'pontos_fortes' => [
                    'Reconhecimento da marca COC e material estruturado Pearson',
                    'Continuidade garantida da Educação Infantil ao Ensino Médio',
                    'Plataforma digital integrada e material apostilado',
                    'Duas unidades no Jardim Guanabara (Rua Marino da Costa e Rua Cambaúba)',
                ],
                'pontos_fracos' => [
                    'Pressão por apostilamento excessivo e conteudismo para crianças pequenas',
                    'Turmas maiores com menor individualização do ritmo de desenvolvimento',
                    'Atendimento institucional mais impessoal e burocrático com as famílias',
                    'Pouco foco no desenvolvimento socioemocional e formação personalizada de caráter',
                ],
                'diferenciais_nossos' => "• Respeito ao Ritmo da Criança: Na Torre de Marfim a infância é valorizada sem a pressão mecânica de apostilas comerciais pesadas.\n• Preceptoria Pessoal: Cada aluno tem atenção individualizada para desenvolver competências cognitivas e emocionais.\n• Acesso Direto aos Educadores: Comunicação aberta e constante com a direção e coordenação, sem intermediários burocráticos.\n• Formação Integral de Valores: Foco em virtudes, disciplina positiva e acolhimento humano.",
                'estrategia_abordagem' => 'Destaque o perigo da aceleração e impessoalidade na primeira infância: "O COC tem um nome forte em vestibulares, mas na Educação Infantil a criança necessita de estímulo sob medida, afeto e desenvolvimento de bases sólidas, e não de virar páginas de apostila padronizada. Na Torre de Marfim seu filho não é um número de matrícula; nós cuidamos da formação dele com dedicação personalizada."',
                'observacoes' => "Endereço: Rua Marino da Costa, 86 (Infantil e Fundamental) e Rua Cambaúba, 380 - Jardim Guanabara, Rio de Janeiro - RJ.\nTelefones: (21) 3383-8059 / (21) 3396-8989.\nSegmento: Educação Infantil ao Ensino Médio.\nSite: coc.com.br",
                'is_ativo' => true,
            ],
            [
                'nome' => 'Maple Bear Canadian School - Ilha do Governador',
                'sigla' => 'Maple Bear',
                'cidade_id' => $cidadeRio?->id,
                'bairro' => 'Jardim Guanabara',
                'faixa_preco' => 'mais_caro',
                'mensalidade_estimada' => 3800.00,
                'proposta_pedagogica' => 'Metodologia canadense de imersão bilíngue com aprendizagem investigativa.',
                'pontos_fortes' => [
                    'Programa oficial de bilinguismo canadense com alta carga horária em inglês',
                    'Grife internacional de grande apelo e prestígio',
                    'Instalações modernas no miolo do Jardim Guanabara (Rua Orestes Barbosa)',
                    'Metodologia centrada em centros de aprendizagem (learning centers)',
                ],
                'pontos_fracos' => [
                    'Mensalidades extremamente altas (fora do orçamento de muitas famílias)',
                    'Taxas anuais elevadas de royalties e materiais importados exclusivos',
                    'Rotatividade de corpo docente e mediadores bilíngues',
                    'Transição para a alfabetização em português muitas vezes exige apoio complementar ou gera insegurança nos pais',
                ],
                'diferenciais_nossos' => "• Relação Custo-Benefício Incomparável: Educação de excelência sem onerar de forma desproporcional o orçamento da família.\n• Excelência em Língua Portuguesa: O método fônico da Torre de Marfim garante sólida proficiência na língua materna e fluência leitora.\n• Parceria Ética com a Família: Filosofia educacional alinhada aos valores familiares e morais da casa dos pais.\n• Estabilidade e Calor Humano: Educadores fixos e dedicados que conhecem a fundo a história de cada criança.",
                'estrategia_abordagem' => 'Mostre prioridade e equilíbrio formativo: "A proposta bilíngue da Maple Bear é interessante, mas muitos pais percebem que, na idade de 1 a 5 anos, o mais importante é estruturar o raciocínio, a segurança emocional e o domínio firme da nossa língua materna e valores. Na Torre de Marfim seu filho terá uma formação humana exemplar e sólida por um investimento justo e equilibrado."',
                'observacoes' => "Endereço: Rua Orestes Barbosa, 102 - Jardim Guanabara, Ilha do Governador, Rio de Janeiro - RJ.\nTelefones: (21) 3827-3737 / (21) 99803-3751.\nSegmento: Toddler, Nursery, Kindergarten e Fundamental.\nSite: maplebear.com.br",
                'is_ativo' => true,
            ],
            [
                'nome' => 'Escola Modelar Cambaúba (Colégio Cambaúba)',
                'sigla' => 'Cambaúba',
                'cidade_id' => $cidadeRio?->id,
                'bairro' => 'Jardim Guanabara',
                'faixa_preco' => 'mais_caro',
                'mensalidade_estimada' => 2600.00,
                'proposta_pedagogica' => 'Tradicional humanista com mais de 60 anos, projetos de musicalização, SOE e horário integral.',
                'pontos_fortes' => [
                    'Mais de seis décadas de tradição e marca histórica na Ilha do Governador',
                    'Unidade exclusiva para Educação Infantil e Integral na Rua Colina, 97',
                    'Serviço de Orientação Educacional (SOE) com psicólogas dedicadas',
                    'Ampla gama de oficinas: jardinagem, culinária, capoeira e musicalização',
                ],
                'pontos_fracos' => [
                    'Instituição de grande porte com centenas de alunos, podendo gerar sensação de dispersão',
                    'Mensalidades e custos de matrícula/materiais na faixa superior da Ilha',
                    'Processos mais engessados e burocracia para atendimento de rotina',
                    'Ambiente competitivo nos anos subsequentes',
                ],
                'diferenciais_nossos' => "• Acolhimento Intimista: Ambiente de acolhimento personalizado onde cada criança e cada família recebem atenção prioritária.\n• Agilidade e Flexibilidade: Contato direto com coordenadores e professores sem intermediários institucionais.\n• Formação com Preceptoria: Acompanhamento da formação do caráter, respeito e virtudes em parceria íntima com os pais.\n• Ensino com Foco no Essencial: Domínio da linguagem, leitura e raciocínio lógico sem dispersão.",
                'estrategia_abordagem' => 'Valide a história do Cambaúba, mas aponte o benefício da escala humana: "O Cambaúba é um colégio tradicional e respeitado na Ilha. No entanto, para os primeiros anos de vida, o que seu filho mais precisa não é de uma megaestrutura com dezenas de turmas, mas de um ambiente seguro e acolhedor onde ele seja chamado pelo nome e acompanhado de perto todos os dias. É essa dedicação que entregamos na Torre de Marfim."',
                'observacoes' => "Endereço: Rua Colina, 97 (Educação Infantil e Integral) / Rua Cambaúba, 560 - Jardim Guanabara, Rio de Janeiro - RJ.\nTelefone: (21) 2468-1260.\nSegmento: Berçário II ao Ensino Médio.\nSite: cambauba.org.br",
                'is_ativo' => true,
            ],
            [
                'nome' => 'Colégio Bretanha',
                'sigla' => 'Bretanha',
                'cidade_id' => $cidadeRio?->id,
                'bairro' => 'Freguesia - Ilha do Governador',
                'faixa_preco' => 'equivalente',
                'mensalidade_estimada' => 1600.00,
                'proposta_pedagogica' => 'Tradicional familiar ("Educação em Família"), fundada em 1967 com forte vertente esportiva.',
                'pontos_fortes' => [
                    'Tradição familiar desde 1967 (antigo Jardim Tio Careca)',
                    'Forte tradição esportiva local (futsal e intercolegial)',
                    'Continuidade de ensino até o Ensino Médio',
                    'Eventos comunitários tradicionais como a Feira Literária (Flib)',
                ],
                'pontos_fracos' => [
                    'Localização na Rua Miritiba (Freguesia), exigindo deslocamento e trânsito para moradores do Jardim Guanabara',
                    'Instalações físicas antigas que demandam reformas de modernização',
                    'Menor especialização na primeira infância comparado a escolas focadas no segmento',
                    'Abordagem pedagógica mais convencional sem métodos inovadores de alfabetização precoce',
                ],
                'diferenciais_nossos' => "• Localização Privilegiada no Jardim Guanabara: Praticidade diária na Rua Gaspar Magalhães, eliminando o trânsito até a Freguesia.\n• Especialização em Educação Infantil: Ambiente, mobiliário e equipe 100% voltados às necessidades motoras e sensoriais de 1 a 5 anos.\n• Método Fônico Consagrado: Eficácia comprovada no desenvolvimento da consciência fonológica e letramento.\n• Instalações Seguras e Aconchegantes: Espaço protegido e monitorado para total tranquilidade dos pais.",
                'estrategia_abordagem' => 'Explore a comodidade logística e o foco especializado: "O Bretanha tem uma história bonita na Freguesia, mas para quem vive no Jardim Guanabara a logística faz toda a diferença na rotina diária da família. Além disso, a Torre de Marfim é pensada exclusivamente para a primeira infância, com foco absoluto no desenvolvimento cognitivo e emocional dos pequenos."',
                'observacoes' => "Endereço: Rua Miritiba, 317 - Freguesia, Ilha do Governador, Rio de Janeiro - RJ.\nTelefones: (21) 3396-1251 / (21) 3396-5594 / (21) 96575-9809.\nSegmento: Educação Infantil ao Ensino Médio.\nSite: bretanha.com.br",
                'is_ativo' => true,
            ],
            [
                'nome' => 'Centro Educacional Caravellas (Escola Caravelas)',
                'sigla' => 'Caravellas',
                'cidade_id' => $cidadeRio?->id,
                'bairro' => 'Tauá - Ilha do Governador',
                'faixa_preco' => 'mais_barato',
                'mensalidade_estimada' => 950.00,
                'proposta_pedagogica' => 'Ensino tradicional com acolhimento comunitário e foco em acessibilidade financeira.',
                'pontos_fortes' => [
                    'Mensalidades altamente acessíveis para a região da Ilha',
                    'Presença consolidada no bairro do Tauá com múltiplas unidades',
                    'Flexibilidade de horários e facilidade nas condições de matrícula',
                    'Ambiente familiar e acessível',
                ],
                'pontos_fracos' => [
                    'Localizado no Tauá (fora do padrão de infraestrutura do Jardim Guanabara)',
                    'Turmas mais cheias e menor proporção de auxiliares por criança',
                    'Estrutura física simples sem áreas verdes integradas ou recursos pedagógicos de ponta',
                    'Proposta pedagógica básica sem acompanhamento sistemático de desenvolvimento integral',
                ],
                'diferenciais_nossos' => "• Padrão de Excelência no Jardim Guanabara: Localização nobre, segura e de fácil acesso para as famílias.\n• Relação Adulto/Criança Otimizada: Turmas com número controlado de alunos para atenção individualizada real.\n• Projeto Pedagógico Estruturado: Método fônico de alfabetização e atividades de estimulação cognitiva diárias.\n• Higiene e Segurança Rigorosas: Ambientes controlados, limpos e climatizados com monitoramento.",
                'estrategia_abordagem' => 'Trabalhe o valor do investimento versus o preço: "A Caravelas atende a um perfil de baixo custo, mas a primeira infância é a janela neurológica mais importante da vida do seu filho. Economizar nesses anos iniciais pode comprometer o desenvolvimento da fala, da autonomia e da alfabetização. Na Torre de Marfim o investimento compensa cada centavo em segurança, cuidado e aprendizado real."',
                'observacoes' => "Endereço: Rua Sobragi, 46 / Rua Haia, 325 - Tauá, Ilha do Governador, Rio de Janeiro - RJ.\nSegmento: Creche, Educação Infantil e Ensino Fundamental.\nRedes: Unidades ativas na Ilha do Governador.",
                'is_ativo' => true,
            ],
            [
                'nome' => 'Escola Martim Pescador (Martins Pescador)',
                'sigla' => 'Martim Pescador',
                'cidade_id' => $cidadeRio?->id,
                'bairro' => 'Jardim Guanabara',
                'faixa_preco' => 'equivalente',
                'mensalidade_estimada' => 1800.00,
                'proposta_pedagogica' => 'Linha sociointeracionista, com valorização da infância, contato com a natureza e brincar ao ar livre.',
                'pontos_fortes' => [
                    'Área arborizada e proposta de contato frequente com a natureza',
                    'Metodologia sociointeracionista com estímulo à criatividade e artes',
                    'Tradição entre famílias do Jardim Guanabara na Rua Ituá',
                    'Atendimento do Infantil ao Fundamental I em ambiente acolhedor',
                ],
                'pontos_fracos' => [
                    'Processo de alfabetização menos sistemático, podendo deixar lacunas para pais que buscam solidez técnica',
                    'Instalações físicas históricas que demandam atualizações prediais',
                    'Menor ênfase em rotinas estruturadas de raciocínio lógico e prontidão leitora',
                    'Relatos de famílias sobre falta de clareza nos marcos de desenvolvimento acadêmico',
                ],
                'diferenciais_nossos' => "• Equilíbrio Perfeito entre Afeto e Método: A Torre de Marfim valoriza a infância e o brincar, mas sem abrir mão da metodologia fônica estruturada.\n• Acompanhamento Transparente de Metas: Pais recebem relatórios claros e frequentes sobre o avanço cognitivo e socioemocional.\n• Formação Moral e de Hábitos: Estímulo à gentileza, cooperação, foco e persistência na rotina diária.\n• Segurança e Cuidado com Espaços Modernos: Ambientes limpos, seguros e com tecnologia apropriada à idade.",
                'estrategia_abordagem' => 'Equilibre o lúdico com a eficácia do aprendizado: "A Martim Pescador tem uma linda proposta ao ar livre. Aqui na Torre de Marfim nós também acreditamos que a criança deve brincar e ser feliz, mas associamos isso a um método consistente que garante que ela chegue aos 5 e 6 anos lendo com facilidade e compreendendo o mundo ao seu redor. A infância é preservada com aprendizado de verdade."',
                'observacoes' => "Endereço: Rua Ituá, 559 - Jardim Guanabara, Ilha do Governador, Rio de Janeiro - RJ.\nTelefones: (21) 3393-8296 / (21) 2462-3950 / (21) 3825-4919.\nSegmento: Educação Infantil e Ensino Fundamental I.\nSite: martimpescador.com.br",
                'is_ativo' => true,
            ],
            [
                'nome' => 'Colégio Iglesias (Creche Tia Luzinete)',
                'sigla' => 'Iglesias',
                'cidade_id' => $cidadeRio?->id,
                'bairro' => 'Jardim Guanabara',
                'faixa_preco' => 'equivalente',
                'mensalidade_estimada' => 1900.00,
                'proposta_pedagogica' => 'Sócio-cognitiva com programa bilíngue (National Geographic Learning) e educação socioemocional.',
                'pontos_fortes' => [
                    'Tradição da marca Creche Tia Luzinete na primeira infância (Rua Jaime Ovalle)',
                    'Parceria de idioma com a National Geographic Learning',
                    'Estrutura com parquinho, pátio coberto e sala de leitura',
                    'Duas unidades próximas no Jardim Guanabara',
                ],
                'pontos_fracos' => [
                    'Custos adicionais relevantes com materiais didáticos de terceiros e programas bilíngues',
                    'Transição recente de gestão entre a creche familiar original e a estrutura maior do colégio',
                    'Rotatividade em cargos de apoio e coordenação pedagógica',
                    'Atendimento menos personalizado devido ao crescimento da instituição',
                ],
                'diferenciais_nossos' => "• Corpo Docente Consolidado: Baixíssima rotatividade de professoras, criando vínculos afetivos duradouros com a criança.\n• Proposta Pedagógica Focada: Alfabetização fônica comprovada sem necessidade de pacotes editoriais caros e pouco eficientes.\n• Comunicação Clara e Direta: Atendimento acolhedor diário da direção sem intermediações comerciais.\n• Ambiente de Calmaria e Segurança: Rotina planejada que reduz a ansiedade e fortalece a autoconfiança da criança.",
                'estrategia_abordagem' => 'Converse sobre consistência e afeto estável: "O Iglesias / Tia Luzinete tem história no bairro, mas muitas famílias nos procuram porque buscam uma escola que mantenha os pés no chão, com estabilidade na equipe de professoras e foco total no desenvolvimento do aluno, sem custos surpresa de materiais ou pacotes complementares. Na Torre de Marfim vocês têm transparência e dedicação de verdade."',
                'observacoes' => "Endereço: Rua General Estilac Leal, 59 e Rua Jaime Ovalle, 160 - Jardim Guanabara, Ilha do Governador, Rio de Janeiro - RJ.\nTelefones: (21) 3393-8950 / (21) 96476-7404.\nSegmento: Berçário (a partir de 4 meses) ao Ensino Médio.\nSite: colegioiglesias.com.br",
                'is_ativo' => true,
            ],
            [
                'nome' => 'Creche Escola Pimpolho',
                'sigla' => 'Pimpolho',
                'cidade_id' => $cidadeRio?->id,
                'bairro' => 'Jardim Guanabara',
                'faixa_preco' => 'equivalente',
                'mensalidade_estimada' => 1750.00,
                'proposta_pedagogica' => 'Mais de 40 anos de tradição em Educação Infantil e Berçário, com horário estendido das 7h às 19h.',
                'pontos_fortes' => [
                    'Mais de quatro décadas de experiência focada exclusivamente em Educação Infantil (3 meses a 5 anos)',
                    'Excelente horário estendido das 7h às 19h para pais com jornada de trabalho ampla',
                    'Localização tradicional na Rua Colina, 36 no Jardim Guanabara',
                    'Diversas atividades complementares (música, capoeira, robótica maker, inglês)',
                ],
                'pontos_fracos' => [
                    'Foco histórico voltado prioritariamente ao cuidado e estada prolongada (perfil creche/hotelzinho)',
                    'Estrutura física tradicional com salas compactas e alto fluxo de crianças em permanência estendida',
                    'Sem continuidade no Ensino Fundamental',
                    'Abordagem pedagógica mais generalista sem método fonológico exclusivo de pré-alfabetização',
                ],
                'diferenciais_nossos' => "• Foco Pedagógico de Alto Nível: Não somos apenas um local de cuidado ou creche de permanência; temos um projeto de ensino estruturado e profundo.\n• Método Fônico com Base Científica: Crianças desenvolvem prontidão leitora com naturalidade e encanto pelos livros.\n• Preceptoria e Hábitos: Formação de virtudes, civilidade e limites saudáveis desde os primeiros passos.\n• Comunicação Personalizada: Diálogo constante com os pais sobre o avanço diário do desenvolvimento.",
                'estrategia_abordagem' => 'Diferencie "cuidar" de "educar com propósito": "A Pimpolho é uma excelente opção para permanência estendida e acolhimento de bebês. Mas quando pensamos no desenvolvimento do intelecto e da personalidade para os anos de formação escolar, a Torre de Marfim oferece um projeto pedagógico incomparável, com método fônico e formação de hábitos que preparam de verdade seu filho."',
                'observacoes' => "Endereço: Rua Colina, 36 - Jardim Guanabara, Ilha do Governador, Rio de Janeiro - RJ.\nTelefones: (21) 2463-1425 / (21) 98463-2795.\nHorário: 7h às 19h.\nSegmento: Berçário e Educação Infantil (3 meses a 5 anos e 11 meses).\nSite: crecheescolapimpolho.com.br",
                'is_ativo' => true,
            ],
            [
                'nome' => 'CAPE Jardim Guanabara (Centro de Atividades Pedagógicas e Esportivas)',
                'sigla' => 'CAPE',
                'cidade_id' => $cidadeRio?->id,
                'bairro' => 'Jardim Guanabara',
                'faixa_preco' => 'equivalente',
                'mensalidade_estimada' => 1550.00,
                'proposta_pedagogica' => 'Ensino tradicional com forte infraestrutura esportiva (piscina, quadra e semi-integral).',
                'pontos_fortes' => [
                    'Infraestrutura física esportiva diferenciada com piscina e quadra poliesportiva',
                    'Localização estratégica no Jardim Guanabara (Rua Dom Antônio de Macedo)',
                    'Opções de horários flexíveis, semi-integral e integral',
                    'Continuidade garantida da Educação Infantil ao Ensino Médio',
                ],
                'pontos_fracos' => [
                    'Espaço compartilhado com alunos mais velhos (Ensino Fundamental e Médio), reduzindo o ambiente protegido de crianças de 1 a 5 anos',
                    'Maior foco institucional no esporte e nos anos finais do que na pedagogia da primeira infância',
                    'Ambiente mais agitado e com maior estímulo sonoro, menos propício para a tranquilidade dos pequenos',
                    'Menor ênfase na formação personalizada socioemocional e pré-alfabetização fônica',
                ],
                'diferenciais_nossos' => "• Ambiente 100% Protegido para a Primeira Infância: Nossos espaços são exclusivos para os pequenos, sem convivência com adolescentes.\n• Calmaria, Foco e Segurança: Clima escolar sereno e acolhedor, propício para concentração, afeto e desenvolvimento emocional saudável.\n• Método Pedagógico Especializado: Toda a energia da equipe está dedicada a atender crianças de 1 a 5 anos.\n• Formação de Valores em Primeiro Lugar: Preceptoria que ensina respeito, paciência, escuta e virtudes familiares.",
                'estrategia_abordagem' => 'Ressalte a segurança e a adequação do ambiente à idade: "O CAPE tem uma excelente infraestrutura esportiva para crianças maiores e adolescentes. No entanto, para crianças de 1 a 5 anos, conviver num colégio grande com centenas de jovens pode ser intimidador e agitado. Na Torre de Marfim o ambiente é 100% protegido e pensado exclusivamente para a primeira infância, com carinho, paz e segurança absoluta."',
                'observacoes' => "Endereço: Rua Dom Antônio de Macedo, 161 e 177 - Jardim Guanabara, Ilha do Governador, Rio de Janeiro - RJ.\nTelefones: (21) 2462-3831 / (21) 2462-4622 / WhatsApp: (21) 96472-4788.\nSegmento: Educação Infantil ao Ensino Médio.\nSite: capejardimguanabara.com.br",
                'is_ativo' => true,
            ],
        ];

        foreach ($concorrentes as $concorrenteData) {
            Concorrente::updateOrCreate(
                ['nome' => $concorrenteData['nome']],
                $concorrenteData
            );
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
