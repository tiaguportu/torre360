# Saúde e Segurança do Trabalhador (Onda 56) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda.

## Contexto

A escola, como empregadora, precisa manter os **exames médicos ocupacionais** dos empregados em dia (NR-7,
PCMSO) e cumprir treinamentos obrigatórios. Para escolas e espaços de recreação infantil há ainda a **Lei
Lucas** (Lei nº 13.722/2018): professores e funcionários precisam ter noções de **primeiros socorros**, com
curso oferecido anualmente para formar ou reciclar parte da equipe. O cadastro de funcionários (Onda 10) não
guarda nada disso, e o vencimento de um exame só é percebido na fiscalização.

## O que já existe

- `Funcionario` (cargo, regime, admissão, desligamento) e o painel de férias (Onda 10), que serve de molde
  para um painel de vencimentos.
- Alertas semanais por e-mail/push só com contagens (`rh:avisar-ferias`).
- **Falta:** exames ocupacionais, treinamentos e certificados.

## Escopo

### Dados
- `ExameOcupacional`: funcionário, tipo (admissional, periódico, retorno ao trabalho, mudança de risco,
  demissional), data, resultado (apto, inapto, apto com restrição), **vencimento** do próximo e o ASO em
  arquivo privado.
- `TreinamentoObrigatorio`: funcionário, tipo (primeiros socorros da Lei Lucas, brigada/combate a incêndio
  e outros definidos pela escola), data, entidade, validade e certificado em arquivo privado.

### Telas
- Painel **Vencimentos de saúde e segurança**: vencidos, vencendo em 60 dias, funcionários sem exame ou
  sem treinamento; filtros por tipo.
- Relatório **Lei Lucas**: quantos professores e funcionários foram capacitados no ano e quem falta.
- Aviso semanal ao RH só com contagens (como o das férias).
- Na admissão, tarefa "agendar exame admissional"; no desligamento, "agendar exame demissional".

## Regras e limites

- O ASO e o resultado são **dado sensível de saúde** (LGPD, art. 11): o RH vê só o resultado apto/inapto e
  o vencimento, nunca diagnóstico; arquivo privado e cifrado, log sem conteúdo.
- A periodicidade do exame periódico é definida no PCMSO pelo médico coordenador: o sistema guarda a data
  do próximo exame informada, sem fixar prazo por cargo.
- Os eventos de saúde do trabalhador do eSocial (S-2220) ficam como evolução; conferir o leiaute vigente
  antes de modelar a exportação.

## Dependências

- Onda 10 (RH) implementada. Sem dependência de outra onda.

## Riscos e observações

- A obrigação de CIPA/PGR depende do porte e do grau de risco da escola: tratar como cadastro de
  documentos, não como motor de regras.
- Terceirizados (limpeza, portaria) têm exames na empresa contratada: fora do escopo.

## Esforço

Pequeno.

## Fusão sugerida

Junto com a [Onda 60](documentos_escola_validade_roadmap.md) e o painel de férias da Onda 10 (já implementado), forma o conjunto **"Vencimentos"**: três telas que fazem a mesma coisa (cadastro com data de vencimento, painel, aviso semanal). Em vez de um painel por assunto, vale um cadastro genérico de vencimento com responsável e antecedência de aviso, em que cada área registra o seu tipo. Cada tipo vira uma fila da Mesa de Pendências (Onda 71), com pouco código.

Veja o [índice das ondas 68 a 71](ondas_68_a_71_indice.md#fusões-sugeridas-para-dar-completude).
