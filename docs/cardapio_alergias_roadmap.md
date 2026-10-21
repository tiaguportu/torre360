# Cardápio Escolar e Alergias (Onda 40)

> **Status: implementado.** Este documento descreve o escopo e as decisões de modelagem;
> detalhes de uso ficam no texto de Ajuda das telas (**Cardápio** e **Restrições alimentares do
> dia**, grupo **Operacional** do menu; **Cardápio** no Portal da Família) e na seção 66 do
> `MANUAL_USUARIO.md`.

## Contexto

Escolas com merenda ou almoço publicam o cardápio e precisam evitar que uma criança com alergia
ou restrição alimentar receba uma refeição que lhe faça mal. O sistema já guardava as restrições na
**ficha médica**, mas a cozinha e a família não as cruzavam com o que seria servido.

## O que já existia

- `FichaMedica`: campos estruturados de alergia (`has_alergia_lactose`, `has_alergia_gluten`,
  `has_alergia_amendoim`) e textos livres (`alergias`, `outras_alergias_alimentares`).
- Políticas de saúde (`docs/lgpd_saude_policies.md`): dado de saúde de menor tem acesso restrito.
- `RefeicaoRotina` (agenda da Educação Infantil): registro do que a criança comeu, sem relação com o
  cardápio.

## O que foi implementado

### Dados

- `cardapios`: `data`, `tipo_refeicao` (café, lanche da manhã, almoço, lanche da tarde, jantar),
  `descricao`, `alergenos` (JSON), `unidade_id` (nulo = todas), `publicado`, `observacoes`.
  Único por (`data`, `tipo_refeicao`, `unidade_chave`), com `unidade_chave` = 0 para "todas".
- `Alergeno`: lactose, glúten, amendoim, ovo, soja, castanhas, peixe, frutos do mar.

### Cruzamento com a ficha médica (`CardapioAlergiaService`)

Três níveis de certeza, para a cozinha não ter falsa segurança:

1. **Confirmado:** o campo estruturado da ficha indica o alérgeno declarado na refeição (hoje só
   lactose, glúten e amendoim têm campo próprio).
2. **Possível:** o texto livre da ficha cita o alérgeno (comparação sem acento e sem caixa). É assim
   que ovo, soja, castanhas, peixe e frutos do mar são cruzados.
3. **Conferir:** há restrição em texto livre que o sistema não consegue ligar a um alérgeno; a
   cozinha confere manualmente.

### Privacidade

- O relatório **Restrições alimentares do dia** exige `VerAlergicos:Cardapio` (só super_admin e
  admin por padrão) e **cada consulta fica no log** (sem o nome de quem tem restrição).
- No **Portal da Família**, o aviso mostra apenas a restrição **do próprio filho**; nenhum dado de
  outro aluno chega ao portal. Só refeições **publicadas** aparecem; respeita a unidade do aluno.

### Telas e permissões

- **Cardápio**: lista por semana, cadastro, publicar, duplicar semana (`CardapioService`).
- **Restrições alimentares do dia**: refeições do dia e alunos afetados por nível.
- **Cardápio** no portal: semana com navegação anterior/próxima.
- `ViewAny/View:Cardapio` (equipe, inclusive professor), `Create/Update/Publicar/Delete/DeleteAny:Cardapio`
  (secretaria e administração), `VerAlergicos:Cardapio` (administração).

## Limitações e próximos passos

- O alérgeno de cada refeição é **declarado por quem cadastra**; o sistema não lê receitas nem
  rótulos. A família é orientada a conferir os ingredientes com a escola.
- Sem ficha técnica de receitas, custo por refeição e estoque de ingredientes.
- Sem restrições religiosas ou de estilo de vida (vegetariano, halal) como categorias próprias.
- Sem aviso ativo (push/e-mail) quando um cardápio com alérgeno do filho é publicado.
