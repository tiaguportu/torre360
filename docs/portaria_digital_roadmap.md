# Portaria Digital (Onda 37) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

A portaria controla quem entra e sai da escola: responsáveis, visitantes, prestadores de serviço,
entregadores, alunos fora do horário. Hoje é caderno de papel. O registro digital dá segurança,
rastro e agilidade, e reduz filas na entrada e na saída.

## O que já existe (ponto de partida)

- `docs/controle_saida_alunos_roadmap.md` (**Onda 9, também não implementada**): autorizados a
  retirar o aluno e registro de cada retirada, manual ou por catraca. A portaria digital deve
  **reaproveitar esse desenho**, não competir com ele.
- `TemplateCracha`/`TemplateCrachaV3`, `QrCodeService` e `BarcodeService`: crachá com QR/código de
  barras.
- `ContatoEmergencia` e `ResponsavelFinanceiro`/`AlunoResponsavel`: pessoas ligadas ao aluno.
- `FrequenciaEscolar`: presença do aluno.
- Não existe registro de visitante, prestador, ocorrência de portaria nem leitura de crachá na entrada.

## Escopo

### Modelos novos
- `PortariaMovimento`: pessoa (cadastrada ou visitante avulso), documento, destino (setor/pessoa),
  motivo, entrada, saída, porteiro, observação.
- `PortariaVisitante`: cadastro reutilizável (nome, documento, empresa, foto opcional), com
  autorização de acesso recorrente (prestador).
- `PortariaPreAutorizacao`: convite gerado pela escola ou pela família com QR de uso único e prazo.

### Telas
- Tela do porteiro, enxuta (leitura de QR/crachá ou busca), com entrada e saída em um toque.
- Painel "quem está na escola agora" (útil em simulado de evacuação).
- Alertas: visitante que não saiu, tentativa de retirada por pessoa não autorizada.
- Relatório de movimento por período.

### Regras
- Retirada de aluno só por pessoa autorizada; fora da lista, exige confirmação da família.
- Foto e documento de visitante: guardar o mínimo necessário e expurgar após o prazo definido.

## Dependências já satisfeitas

- Crachás com QR, cadastro de responsáveis e contatos de emergência.

## Risco e observação

- Implementar junto com a Onda 9: são o mesmo fluxo visto de ângulos diferentes.
- Dado de visitante é dado pessoal: base legal, finalidade e prazo de retenção definidos antes de
  coletar documento e foto.
