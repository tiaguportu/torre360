# Proposta Comercial com Aceite da Família (Onda 87) — Implementada (parcial)

> **Status: implementada em 2026-11-02**, no pacote P10. O ciclo da proposta existente foi completado (link público, aceite, expiração, renovação e do desconto à
> bolsa). O simulador, os indicadores e o aceite com assinatura eletrônica ficaram para depois (ver *Implementação*, no fim). O texto abaixo é o escopo original.
> Veja o [índice dos pacotes P2 e P10](pacotes_p2_p10_indice.md).

## Contexto

Depois da visita, a conversa que decide é a do valor: mensalidade de tabela, desconto, número de parcelas, o que está incluso e até
quando a condição vale. Hoje o consultor monta a proposta, a coordenação aprova o desconto e o consultor **copia o texto para o
WhatsApp**. Dali em diante o sistema não vê mais nada: não sabe se a família abriu, se aceitou ou se a validade passou, e o desconto
negociado **não chega ao contrato**.

## O que já existe (conferido no código)

- **`PropostaComercial`** (`PROP-AAAA-NNNNN`): lead, responsável e aluno, unidade, curso, série, turma e turno, quantidade de
  alunos, **valor de tabela** (preenchido da `mensalidade_base` da turma), desconto fixo ou percentual, valor líquido, parcelas,
  total anual, validade, observações e motivo do desconto. Log de atividades.
- **Alçada de desconto** (`RevenueManagementService`): até 7% o consultor aprova na hora; até 15%, a coordenação; acima, a diretoria.
  Ações **Aprovar** e **Recusar** (com motivo), `StatusPropostaComercial` com nove situações e **`gerarTextoWhatsapp`**.
- Na lista: **WhatsApp** (abre `api.whatsapp.com/send` com o texto pronto), **Ver proposta** (espelho em janela) e **Matricular**, que abre
  o Assistente de Matrícula com `?proposta_id=`.
- O Assistente de Matrícula **pré-preenche** unidade, curso, turma, responsável e aluno a partir da proposta e mostra a mensalidade líquida
  num aviso. Não há, nele, leitura do desconto (a busca por `desconto` e `valor_liquido` no assistente não acha nada).
- **Condição travada:** proposta aceita ou convertida não admite mudança de valor (o serviço recusa).
- Peças prontas para o que falta: **Bolsas** (`BolsaConcedida`, Onda 14, que o gerador de faturas já aplica em cada item), tabela de
  preços da rematrícula (Onda 44), **modelos de documento e assinatura** (Ondas 69 e 74) e o convite de matrícula online.

### O que o enum promete e o código não faz
`AceitaPelaFamilia`, `Convertida` e `Expirada` existem em `StatusPropostaComercial`, mas **nenhum trecho de código muda a proposta
para essas situações** (a busca no `app/` só os acha no próprio enum, na trava de edição e na condição de mostrar o botão *Matricular*). O ciclo para na aprovação.

## Escopo proposto

1. **Proposta como documento.** PDF e página pública com **link seguro** (token, validade): valores, descontos, parcelas, o que está
   incluso, validade e as condições (modelo editável, como as declarações). O envio por e-mail e por WhatsApp passa a levar o link, não
   o texto solto.
2. **Aceite da família.** Botão **Aceitar** na página, com registro de data, IP e quem aceitou (responsável legal), e o aviso ao
   consultor. Opcional: aceite com **assinatura eletrônica** (Onda 69), quando a escola quiser prova mais forte. Aceite muda a situação
   para *Aceita pela família*.
3. **Expiração.** Rotina diária que marca *Expirada* quando passa a validade e **avisa o consultor antes** (3 dias, 1 dia); proposta
   expirada pode ser **renovada** sem refazer a aprovação, se o valor de tabela não mudou.
4. **Da proposta ao contrato.** *Matricular* leva o desconto: ao concluir a matrícula, a proposta vira *Convertida*, e o desconto
   aprovado entra como **bolsa** (tipo "Desconto comercial", vigência e percentual da proposta) para o gerador de faturas aplicar. O
   lead é movido no funil pelo `LeadFunilService`, como a conversão de hoje.
5. **Simulador de mensalidade** (a família vê, o consultor mostra na visita): escolher série e turno, ver tabela, desconto por irmão ou
   por pagamento antecipado (as bolsas já cadastradas) e parcelas; cada simulação pode virar proposta com um clique.
6. **Minha Mesa:** fila *Propostas a vencer* (aceitas não matriculadas, e as que vencem em poucos dias) e *Propostas aguardando
   aprovação* para quem aprova na alçada.
7. **Indicadores:** propostas por situação, tempo da proposta ao aceite e ao contrato, desconto médio concedido e **taxa de conversão
   por faixa de desconto** (o número que mostra se a escola está dando desconto demais).

## Base legal

- **Lei nº 9.870/1999**: o valor da anuidade é fixado e divulgado com antecedência, e a proposta parte desse valor; o desconto
  concedido é uma condição comercial, não um novo preço de tabela.
- **CDC, arts. 30 e 31** (oferta vincula o fornecedor) **e 6º, III** (informação clara): o que a proposta prometer, a escola cumpre;
  por isso o que vai ao contrato **tem de ser** o que foi proposto.
- **LGPD:** a página pública mostra dado de criança e valor; o link tem prazo e não é indexável.

## Dependências e riscos

- **Valor que muda entre a proposta e o contrato** é o risco de maior custo: gerar o contrato com o valor da proposta aceita e **travar**
  quem edita a tabela depois, ou avisar a divergência. A trava de edição da proposta aceita já existe.
- **Desconto como bolsa** mistura duas ideias (comercial e social). Usar um *tipo de bolsa* próprio mantém os relatórios de bolsa
  limpos; decisão de produto a confirmar.
- **Link público de proposta** é superfície nova: token longo, validade, limite de acessos e nada de indexação.
- **Aceite não é contrato.** A proposta aceita é intenção; o contrato continua sendo o documento (e a assinatura da matrícula, o que
  vale). Dizer isso na página.
- Ordem natural: 85 e 86 primeiro (entrada e visita); esta onda é independente delas e pode vir antes se a escola priorizar o
  fechamento.

## Fusão sugerida

**Sequência do CRM (84 → 85 → 86 → 87).** A proposta é o último degrau: o lead entra e é distribuído (85), visita (86), recebe a
proposta e aceita (esta), e o contrato sai pela matrícula online já existente. A rotina de expiração e as filas usam a Minha Mesa da
Onda 84.

## Implementação (2026-11-02)

**Entregue** (`PropostaAceiteService`, `PropostaPublicaController`, `ExpirarPropostasComerciaisCommand`, migration `2026_11_02_130000`):

1. **Página pública `/proposta/{token}`** (token de 48 caracteres, não indexável, só abre proposta aprovada, aceita, convertida ou expirada; as demais respondem 404 como um link que não
   existe). Mostra série, valores, desconto, parcelas, total e validade, sem a alçada nem o motivo do desconto, e diz que o **aceite não substitui o contrato**. A ação *Link para a família*
   gera o link; o texto do WhatsApp passa a levá-lo.
2. **Aceite.** Nome completo e confirmação; grava data, IP e nome, muda para *Aceita pela família* e avisa o consultor. Aceitar duas vezes não duplica o aviso. Proposta vencida não aceita.
3. **Expiração.** `crm:expirar-propostas` (diário) marca *Expirada* a aprovada fora da validade e avisa o consultor **3 e 1 dia antes** (uma vez cada). A **aceita não expira** (a condição foi prometida).
   *Renovar* devolve a proposta à aprovação anterior com nova validade (padrão 5 dias) **sem refazer a aprovação**, desde que a mensalidade de tabela da turma não tenha mudado.
4. **Da proposta ao contrato.** No Assistente de Matrícula (`?proposta_id=`), ao criar a matrícula a proposta vira *Convertida* e o desconto vira a bolsa **"Desconto comercial"**
   (percentual da proposta, pelo número de parcelas a partir de hoje), que o gerador de faturas já aplica. Para mais de um irmão, cada matrícula recebe a bolsa. **Percentual inteiro:** o desconto
   fixo ou fracionado é arredondado (R$ 75 sobre R$ 1.000 vira 8%), e a observação da bolsa e o aviso dizem isso. Proposta vencida ou não aprovada: a matrícula segue sem o desconto, com aviso persistente.
6. **Minha Mesa:** *Propostas a vencer* (aprovadas a 3 dias do fim e aceitas ainda sem matrícula; o consultor vê as suas) e *Propostas para aprovar* (só as que a alçada do usuário cobre).

**Fora desta entrega:** 5 (simulador de mensalidade), 7 (indicadores e taxa de conversão por faixa de desconto), PDF da proposta e aceite com assinatura eletrônica (Onda 69).

**Testes:** `PropostaAceiteTest`.
