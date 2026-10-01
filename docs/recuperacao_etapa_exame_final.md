# Recuperação por Etapa e Exame Final

Onda 5 do roadmap de funcionalidades. Estende o Fechamento do Ciclo Letivo
(`FechamentoCicloService`, página **Acadêmico → Fechamento do Ciclo Letivo**) com dois
mecanismos de recuperação que já existiam parcialmente: recuperação **anual** (todas as
etapas somadas) e nenhum exame final. Esta onda adiciona a recuperação **por etapa** e o
**exame final**, ambos configuráveis por `PeriodoLetivo` — o comportamento anterior
continua sendo o padrão para períodos já existentes.

> Os demais itens do plano original desta onda (editor de documentos pedagógicos e
> solicitação de documentos por protocolo) já existiam no sistema, implementados pela
> "Secretaria Digital" (`TemplateDocumento`/`SolicitacaoDocumento`, ver
> `GEMINI_DB.md` seção 12) — por isso não fazem parte desta onda. O que esta onda faz é
> **ligar** o histórico escolar real (`SituacaoFinalDisciplina`) a esse recurso já
> existente (seção 3 abaixo).

## 1. Recuperação por Etapa (vs. Recuperação Anual)

Configuração: toggle **"Recuperação por etapa"** no cadastro de `PeriodoLetivo`
(`recuperacao_por_etapa`, boolean, padrão `false`).

- **Desligado (padrão) — "recuperação anual":** comportamento pré-existente. Todas as
  avaliações de categoria "recuperação" (`CategoriaAvaliacao.eh_recuperacao`) do período,
  não importa em qual etapa foram lançadas, são somadas num único valor consolidado, que
  substitui a **menor** média de etapa do aluno — desde que seja melhor que ela. Só uma
  etapa é recuperada, mesmo que o aluno tenha ido mal em mais de uma.
- **Ligado — "recuperação por etapa":** cada avaliação de recuperação é associada à etapa
  em que foi lançada (mesmo campo `etapa_avaliativa_id` de sempre) e só pode substituir a
  média **daquela mesma etapa**. Permite recuperar mais de uma etapa de forma
  independente, e cada etapa só é substituída se a sua própria recuperação for melhor que
  a média original dela.

Nos dois modos, o cálculo continua sendo feito por
`FechamentoCicloService::calcularSituacaoFinal()`; a única mudança é qual etapa cada nota
de recuperação pode substituir. `App\Models\PeriodoLetivo::recuperacao_por_etapa`
controla o modo; trocar a configuração não recalcula automaticamente fechamentos já
feitos — é preciso rodar **"Calcular Situação Final"** de novo.

## 2. Exame Final

Configuração no cadastro de `PeriodoLetivo`:

- **"Permitir exame final"** (`exame_final_habilitado`, boolean, padrão `false`).
- **"Nota Mínima para Aprovação após o Exame Final"** (`nota_aprovacao_pos_exame`,
  decimal, padrão `5.00`) — só aparece quando o exame final está habilitado.

Quando habilitado, uma disciplina que ficar em situação **Recuperação** após o
fechamento do ciclo pode receber a nota do exame final diretamente na tela de
**Fechamento do Ciclo Letivo** — aparece um botão **"Lançar Exame Final"** na coluna
correspondente para cada linha elegível.

- **Fórmula (fixa nesta versão):** média simples entre a média final do período e a nota
  do exame — `(media_final + nota_exame_final) / 2`. O resultado é comparado à nota
  mínima pós-exame configurada.
- **Só dois desfechos possíveis:** Aprovado ou Reprovado — não existe uma segunda
  recuperação depois do exame final.
- **Não pode ser lançado duas vezes**: depois de lançado, a coluna passa a mostrar o
  resultado definitivo (situação + média pós-exame) no lugar do botão.
- **Recalcular o fechamento preserva o exame já lançado**, contanto que a disciplina
  continue em situação de Recuperação. Se o recálculo mudar a situação para Aprovado ou
  Reprovado diretamente (por exemplo, uma nota lançada depois foi corrigida), um exame
  final já lançado é descartado — ele deixou de fazer sentido para a nova situação.

Implementado em `FechamentoCicloService::elegivelExameFinal()` e
`FechamentoCicloService::registrarExameFinal()`; UI em
`App\Filament\Pages\FechamentoCicloLetivo::lancarExameFinalAction()`.

## 3. Histórico Escolar real na Secretaria Digital

A macro `{{TABELA_HISTORICO}}` usada pelos templates de documento do tipo **Histórico
Escolar** (`DocumentoService::gerarTabelaHistoricoHtml()`) antes mostrava uma lista fixa
de disciplinas com a situação sempre "Regular" (placeholder). Agora ela busca os dados
reais:

- Uma tabela por matrícula do aluno (um ano/período letivo cada), na ordem cronológica.
- Para cada disciplina, mostra a **média final** e a **situação** já gravadas em
  `SituacaoFinalDisciplina` pelo Fechamento do Ciclo Letivo.
- Quando a disciplina teve exame final, mostra o resultado **pós-exame**
  (`media_final_pos_exame`/`situacao_final_pos_exame`), não a situação de Recuperação
  anterior a ele — esse é o resultado definitivo.
- Um período sem nenhum fechamento calculado ainda aparece com o aviso "Situação final
  ainda não calculada para este período" em vez de uma tabela vazia ou inventada.

Esse histórico só existe a partir do momento em que o Fechamento do Ciclo Letivo é
rodado para o período em questão — é a mesma fonte de dados que a tela de fechamento
usa, então os dois lugares sempre mostram a mesma coisa.

## 4. Migrations desta onda

| Migration | O que faz |
|---|---|
| `add_recuperacao_exame_final_to_periodo_letivo_table` | Colunas `recuperacao_por_etapa`, `exame_final_habilitado`, `nota_aprovacao_pos_exame` em `periodo_letivo`. |
| `add_exame_final_to_situacao_final_disciplina_table` | Colunas `nota_exame_final`, `media_final_pos_exame`, `situacao_final_pos_exame` em `situacao_final_disciplina`. |

## 5. Testes

`FechamentoCicloServiceTest` (recuperação anual vs. por etapa, persistência do
fechamento, exame final — sucesso/reprovação/falhas de validação, preservação e limpeza
do exame final em recálculos), `FechamentoCicloLetivoPageTest` (fluxo completo pela
página: calcular → lançar exame final), `DocumentoServiceHistoricoTest` (histórico real
multi-ano, resultado pós-exame prevalece sobre recuperação, aviso de período não
fechado).
