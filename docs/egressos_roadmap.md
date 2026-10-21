# Egressos (Onda 36) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Quem concluiu o ensino médio (ou deixou a escola) é prova viva da qualidade do ensino e uma fonte
de indicações, mentoria e eventos de reencontro. Hoje o sistema "esquece" o aluno quando a matrícula
é encerrada: não há cadastro de egressos, contato atualizado nem comunicação com consentimento.

## O que já existe (ponto de partida)

- `Matricula` com situação final, `HistoricoEscolar*` e `SolicitacaoDocumento` (egresso pede
  histórico e declarações).
- `TransferenciaEscolar`: saída antes da conclusão.
- `Interessado`, `IndicacaoInteressado`, `IndicacaoCaptacaoService` e `CampanhaMarketing`: o CRM e a
  captação por indicação, que podem usar egressos como indicadores.
- `ComunicacaoEmMassa` e `ConsentimentoMatricula`: comunicação com base de consentimento.
- Não existe a figura do egresso, contato pós-saída nem destino acadêmico/profissional.

## Escopo

### Modelos novos
- `Egresso`: `pessoa_id`, ano e série de conclusão, motivo de saída (conclusão, transferência,
  evasão), contato atual (e-mail, telefone), situação atual (estuda, trabalha), instituição/curso,
  consentimento de contato e de uso de depoimento.
- `EgressoInteracao`: contatos feitos pela escola (evento, pesquisa, convite).

### Telas
- Geração automática do egresso ao concluir o ciclo (`FechamentoCicloService`).
- Página de atualização de dados (link único enviado por e-mail).
- Pesquisa de percurso (curso, trabalho) usando o motor de questionários.
- Convites para eventos, palestras de ex-alunos e programa de indicação.
- Mural de depoimentos (apenas com consentimento).

### Regras
- Contato só com base legal: consentimento ou legítimo interesse documentado, sempre com
  descadastro (LGPD).
- Egresso maior de idade consente em nome próprio; para quem saiu menor, a escola mantém só o
  necessário até a maioridade.
- Dados acadêmicos continuam sob guarda legal da escola, independentemente do cadastro de egresso.

## Dependências já satisfeitas

- Fechamento de ciclo, questionários, CRM e comunicação em massa.

## Risco e observação

- Qualidade da base: sem rotina de atualização, os contatos ficam velhos rápido. Medir taxa de
  retorno dos e-mails desde o início.
