<?php

/*
|--------------------------------------------------------------------------
| Admissão e desligamento de funcionários (Onda 65)
|--------------------------------------------------------------------------
|
| Lista de providências de cada processo. Ao iniciar, o sistema cria um item para cada linha abaixo; depois a escola
| pode acrescentar itens, marcar como feito, anexar o comprovante ou marcar "não se aplica" com uma justificativa. É um
| ponto de partida para a rotina de uma escola privada que contrata pela CLT: confira com o contador e o jurídico antes de
| adotar, e mude o que não valer para a sua escola.
|
| Cada item: categoria (documento, exame, contrato, acesso, equipamento, treinamento, financeiro, outro), titulo,
| orientacao (uma linha de ajuda), obrigatorio (impede concluir o processo enquanto não for feito) e prazo_dias (dias a
| partir da data do processo: negativo é antes, positivo é depois; a data do processo é o primeiro dia de trabalho na
| admissão e o último dia do contrato no desligamento).
*/

return [

    'admissao' => [
        ['categoria' => 'documento', 'titulo' => 'Documentos pessoais', 'orientacao' => 'RG, CPF, título de eleitor, certidão de nascimento ou casamento e, se for o caso, certificado de reservista.', 'obrigatorio' => true, 'prazo_dias' => -3],
        ['categoria' => 'documento', 'titulo' => 'PIS/NIS e CTPS (digital)', 'orientacao' => 'A CTPS é digital: confira o CPF e o PIS/NIS no cadastro do empregado.', 'obrigatorio' => true, 'prazo_dias' => -3],
        ['categoria' => 'documento', 'titulo' => 'Comprovante de residência e foto 3x4', 'orientacao' => null, 'obrigatorio' => true, 'prazo_dias' => -3],
        ['categoria' => 'documento', 'titulo' => 'Diploma e registro profissional (docentes)', 'orientacao' => 'Professor: diploma ou certificado de habilitação para a etapa em que vai lecionar.', 'obrigatorio' => false, 'prazo_dias' => -3],
        ['categoria' => 'documento', 'titulo' => 'Certidões dos filhos menores de 14 anos e cartão de vacinação', 'orientacao' => 'Para o salário-família: peça só se o empregado tiver direito.', 'obrigatorio' => false, 'prazo_dias' => 30],
        ['categoria' => 'exame', 'titulo' => 'Exame médico admissional (ASO)', 'orientacao' => 'Antes de o empregado começar a trabalhar (NR-7). Guarde o atestado no prontuário.', 'obrigatorio' => true, 'prazo_dias' => -1],
        ['categoria' => 'contrato', 'titulo' => 'Contrato de trabalho assinado', 'orientacao' => 'Duas vias assinadas; uma fica com o empregado.', 'obrigatorio' => true, 'prazo_dias' => 0],
        ['categoria' => 'contrato', 'titulo' => 'Registro no eSocial antes do primeiro dia', 'orientacao' => 'Evento de admissão (S-2200), até o dia anterior ao início. A CTPS digital é atualizada por ele.', 'obrigatorio' => true, 'prazo_dias' => -1],
        ['categoria' => 'contrato', 'titulo' => 'Jornada no ponto eletrônico', 'orientacao' => 'Cadastre a jornada e o acordo de banco de horas em RH > Funcionários.', 'obrigatorio' => true, 'prazo_dias' => 0],
        ['categoria' => 'financeiro', 'titulo' => 'Dados bancários para o pagamento do salário', 'orientacao' => null, 'obrigatorio' => true, 'prazo_dias' => -3],
        ['categoria' => 'financeiro', 'titulo' => 'Declaração de dependentes (Imposto de Renda) e opção pelo vale-transporte', 'orientacao' => 'Colha a declaração assinada ou a recusa por escrito.', 'obrigatorio' => false, 'prazo_dias' => 7],
        ['categoria' => 'acesso', 'titulo' => 'Acesso ao Torre360', 'orientacao' => 'Crie o usuário, vincule à pessoa e escolha o papel (professor, secretaria...).', 'obrigatorio' => true, 'prazo_dias' => 0],
        ['categoria' => 'acesso', 'titulo' => 'E-mail institucional e grupos de comunicação', 'orientacao' => null, 'obrigatorio' => false, 'prazo_dias' => 0],
        ['categoria' => 'equipamento', 'titulo' => 'Crachá, chaves, uniforme e equipamentos', 'orientacao' => 'Registre o que foi entregue na observação.', 'obrigatorio' => false, 'prazo_dias' => 0],
        ['categoria' => 'treinamento', 'titulo' => 'Regimento, proteção de dados (LGPD) e uso de imagem dos alunos', 'orientacao' => 'Leitura e termo de confidencialidade assinado, conforme a política da escola.', 'obrigatorio' => true, 'prazo_dias' => 7],
        ['categoria' => 'treinamento', 'titulo' => 'Noções de primeiros socorros (Lei Lucas)', 'orientacao' => 'A Lei nº 13.722/2018 exige essa capacitação de professores e funcionários de escolas de educação básica.', 'obrigatorio' => true, 'prazo_dias' => 90],
    ],

    'desligamento' => [
        ['categoria' => 'contrato', 'titulo' => 'Comunicação do desligamento e aviso prévio', 'orientacao' => 'Pedido de demissão ou carta de dispensa. O aviso prévio é de 30 dias, mais 3 dias por ano de serviço (Lei nº 12.506/2011). Em término de contrato por prazo determinado, marque "não se aplica".', 'obrigatorio' => true, 'prazo_dias' => -30],
        ['categoria' => 'financeiro', 'titulo' => 'Conferir férias, 13º salário e banco de horas a pagar', 'orientacao' => 'Veja o Painel de Férias e o saldo do banco de horas antes de calcular a rescisão.', 'obrigatorio' => true, 'prazo_dias' => -5],
        ['categoria' => 'exame', 'titulo' => 'Exame médico demissional (ASO), quando exigido', 'orientacao' => 'A NR-7 dispensa o exame quando o último exame ocupacional é recente: confirme o prazo com a medicina do trabalho.', 'obrigatorio' => true, 'prazo_dias' => 0],
        ['categoria' => 'contrato', 'titulo' => 'Termo de rescisão (TRCT) assinado', 'orientacao' => 'Calcule as verbas e colha a assinatura.', 'obrigatorio' => true, 'prazo_dias' => 10],
        ['categoria' => 'financeiro', 'titulo' => 'Pagamento das verbas rescisórias', 'orientacao' => 'Até 10 dias depois do fim do contrato (CLT, art. 477, § 6º).', 'obrigatorio' => true, 'prazo_dias' => 10],
        ['categoria' => 'contrato', 'titulo' => 'Baixa do contrato no eSocial', 'orientacao' => 'Evento de desligamento (S-2299), em até 10 dias do término.', 'obrigatorio' => true, 'prazo_dias' => 10],
        ['categoria' => 'financeiro', 'titulo' => 'Documentos para saque do FGTS e seguro-desemprego', 'orientacao' => 'Entregue o que a modalidade da rescisão dá direito. Na justa causa, marque "não se aplica".', 'obrigatorio' => true, 'prazo_dias' => 10],
        ['categoria' => 'acesso', 'titulo' => 'Retirar os acessos fora do Torre360', 'orientacao' => 'E-mail institucional, grupos de comunicação, redes sociais e chaves. Os acessos ao Torre360 saem ao concluir este processo.', 'obrigatorio' => true, 'prazo_dias' => 0],
        ['categoria' => 'equipamento', 'titulo' => 'Devolução de crachá, chaves, uniforme e equipamentos', 'orientacao' => null, 'obrigatorio' => false, 'prazo_dias' => 0],
        ['categoria' => 'treinamento', 'titulo' => 'Passar turmas, planos de aula e pendências (docentes)', 'orientacao' => 'Reatribua as aulas na grade horária antes da saída.', 'obrigatorio' => false, 'prazo_dias' => -7],
        ['categoria' => 'documento', 'titulo' => 'Entrevista de desligamento', 'orientacao' => 'Opcional: ajuda a escola a entender por que as pessoas saem.', 'obrigatorio' => false, 'prazo_dias' => -3],
        ['categoria' => 'documento', 'titulo' => 'Arquivar o prontuário do empregado', 'orientacao' => 'Documentos de admissão, ponto e rescisão, pelo prazo da tabela de guarda da escola.', 'obrigatorio' => false, 'prazo_dias' => 10],
    ],

];
