# Conferência de Cláusulas dos Modelos de Contrato (Onda 66) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda.

## Contexto

O contrato de matrícula é um **contrato de adesão** de consumo: a família não negocia as cláusulas, e a lei
protege quem aderiu. Escolas costumam reaproveitar o modelo de anos anteriores, de outras escolas ou de
associações, e é aí que entram cláusulas que a lei torna **nulas**: multa de mora acima de 2%, retenção de
documentos por inadimplência, material de uso coletivo cobrado à parte, perda de todas as parcelas pagas na
rescisão, foro distante do domicílio da família, autorização genérica e perpétua de uso de imagem. O sistema
monta e assina o contrato, mas **não olha o que está escrito no modelo**.

Base legal (a conferir com o jurídico da escola):

- **CDC, art. 51** (cláusulas nulas, em especial o inciso IV, obrigações iníquas ou abusivas, e o XV, em
  desacordo com o sistema de proteção do consumidor), **art. 52, § 1º** (multa de mora de no máximo 2%),
  **arts. 46 e 54** (ciência prévia do conteúdo; contrato de adesão com cláusulas limitativas em destaque).
- **Lei nº 9.870/1999**: art. 1º, § 7º (material de uso coletivo), art. 2º (divulgação do valor com 45 dias de
  antecedência) e art. 6º (nada de suspender prova, reter documento ou desligar por inadimplência durante o
  ano letivo).
- **CDC, art. 39, I**: venda casada (obrigar a comprar uniforme ou livro da escola).
- **LGPD, art. 8º, § 4º**: autorizações genéricas para tratamento de dados são nulas.
- **Código Civil, art. 413**: a penalidade excessiva pode ser reduzida.

## O que já existe

- `TemplateContrato` (nome, conteúdo em HTML e a marca de modelo padrão), com a ação de **clonar**, e o
  `ContractTemplateService`, que preenche as macros (`{{!! $variavel !!}}`) e gera o PDF.
- `HtmlSanitizer`, o contrato por aluno (`Contrato`) e, desde a Onda 62, os campos de **multa e juros por
  contrato**, que ficam congelados na assinatura.
- A **trava de itens de uso coletivo** da lista de material (Onda 59) é o modelo do que se quer aqui: regras
  por termo, severidade, base legal e orientação, em arquivo de configuração.
- **Falta:** qualquer leitura do texto do modelo, qualquer alerta e qualquer registro de que alguém conferiu.

## Escopo

### Regras (`config/contrato_clausulas.php`)
Cada regra tem: tema, severidade (**atenção** ou **bloqueia ao tornar padrão**), termos ou expressões, a base
legal e a orientação em uma linha. Exemplos de partida:

| Tema | O que procura | Base |
|---|---|---|
| Multa de mora | percentual perto de "multa" acima de 2% | CDC, art. 52, § 1º |
| Retenção e penalidade pedagógica | "reter documentos", "suspensão de provas", "negar a rematrícula" por débito | Lei nº 9.870/1999, art. 6º |
| Desligamento por inadimplência | desligar o aluno antes do fim do ano letivo | Lei nº 9.870/1999, art. 6º, § 1º |
| Material de uso coletivo | cláusula que pede material de limpeza, papel higiênico etc. | Lei nº 9.870/1999, art. 1º, § 7º |
| Venda casada | obrigar a compra de uniforme, livro ou apostila da escola ou de fornecedor indicado | CDC, art. 39, I |
| Rescisão | "perda das parcelas pagas", multa sem proporção | CDC, art. 51; CC, art. 413 |
| Reajuste | reajuste durante o ano letivo ou sem a forma da lei | Lei nº 9.870/1999, art. 1º e 2º |
| Foro | foro de eleição fora do domicílio da família | CDC, art. 51, IV |
| Dados e imagem | autorização "ampla, geral e irrestrita" ou perpétua | LGPD, art. 8º, § 4º |
| Isenção de responsabilidade | a escola "não responde por qualquer dano" | CDC, art. 51, I |

### Conferência
- `ConferenciaContratoService::conferir(TemplateContrato)`: tira o texto do HTML, separa por cláusula
  (títulos "CLÁUSULA" e numeração), aplica as regras e devolve os alertas com o **trecho**, a base legal e a
  orientação.
- Confere também **números e macros**: multa e juros do texto contra o padrão da escola e os campos de contrato
  (Onda 62); macros que não existem; cláusulas esperadas que faltam (objeto, valor e forma de pagamento,
  reajuste, rescisão, proteção de dados, foro).

### Telas
- Ação **Conferir cláusulas** na lista e na edição do modelo: modal com os alertas por severidade e o trecho
  marcado.
- Tabela `conferencias_template_contrato`: modelo, **hash do conteúdo**, resultado, quem conferiu e quando. O
  modelo mostra "conferido em dd/mm por X, N alertas" e avisa quando o conteúdo mudou depois da última conferência.
- Ao marcar um modelo como **padrão**, o sistema exige uma conferência do conteúdo atual e impede o padrão com
  alerta de "bloqueia" não revisado (com justificativa registrada, para o caso de a escola discordar).
- Comparação (diff) entre duas versões do modelo, para o ajuste do ano.

## Regras e limites

- É um **apoio**, como a trava do material: falso positivo e falso negativo existem, e a tela diz que não
  substitui o jurídico. A lista de termos e as exceções ficam em configuração para ajustar sem código.
- O resultado guarda o **trecho do modelo** (que é texto da escola), nunca dado de aluno. Se um dia a conferência
  usar IA, só o texto do modelo vai, sem dados pessoais, e com o aceite de IA da escola.
- Não altera o modelo e não impede a assinatura de contratos já gerados.

## Dependências

- Onda 62 (multa e juros por contrato) e Onda 59 (o padrão de regras por termo). Nenhuma é bloqueante.

## Riscos e observações

- Cláusula de rescisão e de multa depende do contrato: o sistema não decide o valor, só aponta o que parece
  desproporcional.
- Redação por extenso ("dez por cento") escapa de regra numérica: a regra de multa também procura os números por
  extenso mais comuns.

## Esforço

Pequeno a médio (regras e serviço pequenos; a tela e o registro da conferência são o grosso).
