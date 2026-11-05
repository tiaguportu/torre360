# Exclusão protegida de matrícula e valor da fatura em centavos (2026-11-05)

> **Status: implementado.** Saiu da segunda análise de 2026-11-05 (itens 1 e 2), que seguiu a de
> [frequencia_unica_e_fila_longa.md](frequencia_unica_e_fila_longa.md). Duas correções independentes, publicadas juntas:
> uma de integridade de dados (matrícula) e uma de aritmética do dinheiro (fatura).

## 1. Matrícula: quem tem histórico não é excluído

### O problema

`Matricula` não tem *soft delete*, e quase tudo que depende dela foi criado com `cascadeOnDelete`: excluir a linha
apagava junto, sem aviso, a frequência, os resultados finais, as ocorrências, as bolsas, as transferências, os acordos de
inadimplência, os empréstimos e outros registros. O contrato ainda ficava órfão. A ação de excluir (na edição e em lote)
era protegida apenas pela permissão `Delete:Matricula`, sem olhar o que seria perdido. Uma matrícula já cancelada nem
chegava a ser apagada: `cancelamentos_matricula` usa `restrictOnDelete`, e o banco devolvia um erro de integridade
(erro 500 para quem clicava).

A `Fatura` já tinha o padrão certo (gancho `deleting` + ação oculta); a matrícula não.

### O que mudou

| Onde | Mudança |
|---|---|
| `Matricula::HISTORICO_QUE_IMPEDE_EXCLUSAO` | Lista de 23 tabelas de histórico acadêmico, financeiro e legal (frequência, notas, resultado final, ocorrências, pareceres, portfólio, atestados, Conselho Tutelar, transferências, rematrículas, bolsas, empréstimos, acordos, contrato, cancelamentos, solicitações de documentos, Censo, correções de gabarito, reuniões, rotina diária, adaptações curriculares e consentimentos). |
| `Matricula::possuiHistorico()` / `historicoQueImpedeExclusao()` | Dizem se há histórico e quanto de cada tipo (`['registros de frequência' => 12, 'contratos' => 1]`). |
| Gancho `deleting` | Última defesa: lança `LogicException` com a lista do que existe. Vale para qualquer caminho (tela, tinker, código). |
| `ExcluirMatriculaAction` / `ExcluirMatriculasEmLoteAction` | Ações do Filament usadas em **todas** as telas que excluem matrícula: edição da matrícula, lista de matrículas, aba *Matrículas* da pessoa e aba *Matrículas* da turma. No modal aparece o que impede a exclusão; ao confirmar, nada é apagado e surge o aviso *"Matrícula não pode ser excluída"* com o caminho certo. Em lote, só as matrículas sem histórico são excluídas e o aviso diz quantas ficaram. |
| `ExcluirPessoaAction` | A lista de pessoas já recusava excluir quem tem matrícula, contrato, vínculo familiar ou usuário (`Pessoa::getInviabilityReasons()`), mas a **edição da pessoa** tinha um botão Excluir sem essa conferência, e o banco apaga as matrículas da pessoa em cascata. Agora os dois lugares usam a mesma ação. |
| Ajuda da edição | O texto do botão **Excluir** explica a regra. |

Matrícula **sem** histórico (criada por engano, sem nada lançado) continua podendo ser excluída; os registros
operacionais dela (tarefas de retenção, designação de professor de preceptoria, exigências de documento) vão junto.

### O que fazer com uma matrícula que não pode ser excluída

Use o **Cancelamento de Matrícula** (Secretaria → *Cancelamento de Matrícula*, seção 74 do manual): ele encerra o vínculo,
decide o destino de cada fatura e preserva todo o histórico.

### Teste de guarda

`MatriculaExclusaoProtegidaTest::test_toda_tabela_que_aponta_para_matricula_esta_classificada` percorre o esquema e falha
quando uma tabela nova tem chave (ou coluna `matricula_id`) para `matricula` e não está em
`HISTORICO_QUE_IMPEDE_EXCLUSAO` nem na lista de descartáveis do próprio teste. Quem criar uma tabela ligada à matrícula é
obrigado a decidir. Outro teste confere que toda tabela e coluna da lista existe (uma tabela renomeada viraria erro de
SQL na hora de excluir).

### Limites conhecidos

- A regra vale para o modelo `Matricula` e para as telas acima. Apagar uma **Pessoa** por código (`$pessoa->delete()`,
  tinker) ainda apaga as matrículas dela por `cascade` no banco, sem passar pelo gancho; pelas telas a pessoa com matrícula
  é recusada. Apagar uma **Turma** ou um **Período Letivo** também depende de `cascade`/`restrict` do banco e não foi revisto.
- A lista cobre as tabelas do esquema de teste (SQLite). Tabelas que só existam em produção não são vistas pelo teste de guarda.
- `nota.matricula_id` não tem chave estrangeira no esquema de teste; o histórico dela está na lista pela coluna.

## 2. Fatura: o valor é sempre em centavos inteiros

### O problema

`Fatura::valor` somava `valor_unitario × quantidade − desconto` em ponto flutuante, **sem arredondar**. O desconto
percentual (bolsa) deixa fração de centavo, e a soma deixa resíduo de ponto flutuante. A quitação é `valor_restante <= 0`
em `BaixaFaturaService` (e em `ReguaCobrancaService`), sem tolerância. Resultado: a família paga exatamente o valor que
vê, e a fatura fica **Parcial** com saldo de 0,000000000000028 ou de 0,004; a régua de cobrança continua avisando.

Simulação da mesma aritmética (preço de R$ 300 a R$ 5.000, bolsa inteira de 1% a 50%):

| Preço | Combinações com saldo > 0 depois de pagar o valor exibido |
|---|---|
| Múltiplo de R$ 10,00 | 3,3% (resíduo de ponto flutuante) |
| Múltiplo de R$ 1,00 | 6,1% |
| Com centavos | 46,1% (fração de centavo) |

Exemplos reproduzíveis: R$ 310,00 com bolsa de 24% (valor interno `235.60000000000002`; saldo `2.8e-14`) e R$ 800,02 com
30% (valor interno `560.014`; a família paga R$ 560,01 e sobram `0.004`). Não foi achado caso nas mensalidades redondas
comuns (R$ 800 a R$ 1.500 com bolsas de 5 em 5%), então o defeito aparece sobretudo com bolsas quebradas e preços
com centavos. **Não foi medido em produção.**

### O que mudou

| Onde | Mudança |
|---|---|
| `ItemFatura::calcularTotalLiquido()` | Regra única do total do item: bruto − desconto, arredondado a centavos (o mesmo arredondamento do `number_format`, então o valor interno passa a ser o que a família já via). `totalBruto()` e `totalLiquido()` usam essa regra por item. |
| `Fatura::valor`, `valor_bruto`, `valor_pago`, `valor_restante` | Todos em centavos inteiros: cada item é arredondado antes da soma e o saldo é `round(valor − pago, 2)`. Fatura paga pelo valor cobrado dá `valor_restante === 0.0`. |
| Formulário da fatura (`FaturaForm`) | "Total Item" e "Valor Total Consolidado" usam a mesma regra, para a tela e o valor gravado não divergirem. |
| `faturas:corrigir-quitadas` | Comando que acha as faturas **Parcial** que já foram pagas por inteiro e as marca como **Pagas**. Sem `--aplicar` só lista. |

Nada foi mudado em `BaixaFaturaService` nem em `ReguaCobrancaService`: com o saldo em centavos inteiros, `<= 0` passa a
estar certo. As consultas SQL de divergência de contrato (`Contrato::scopeComFaturamentoDivergente`) já usavam tolerância de
R$ 0,01 e ficaram como estavam.

### Para o financeiro: faturas que já ficaram Parcial

A correção vale daqui para frente e também recalcula o saldo de faturas antigas (ele é calculado na hora, não gravado).
O **status** gravado, porém, só muda na próxima baixa. Para acertar as antigas:

```
php artisan faturas:corrigir-quitadas              # lista (nada é gravado)
php artisan faturas:corrigir-quitadas --aplicar    # grava (pede confirmação; --force dispensa)
```

Só entram faturas que estão **Parcial**, têm pelo menos um recebimento e saldo zerado. Parcial sem nenhum recebimento ou
com saldo de verdade fica como está, para revisão. A mudança de status passa pelo histórico de alterações da fatura.

### Limites conhecidos

- O desconto percentual é aplicado sobre o item e arredondado no total do item; não há distribuição de centavos entre itens.
- Quem lê `valor_restante` como número exato deve esperar centavos inteiros (ex.: `135.6`), nunca mais `135.60000000000002`.
- O gateway de pagamento real continua pendente (driver fictício); o valor que ele cobrará é este.

## Arquivos

- `app/Models/Matricula.php`, `app/Filament/Resources/Matriculas/Actions/ExcluirMatriculaAction.php`,
  `app/Filament/Resources/Matriculas/Actions/ExcluirMatriculasEmLoteAction.php`,
  `app/Filament/Resources/Pessoas/Actions/ExcluirPessoaAction.php` e as telas que as usam (`EditMatricula`,
  `MatriculasTable`, `EditPessoa`, `PessoasTable` e os gerenciadores de relação de matrículas da pessoa e da turma)
- `app/Models/ItemFatura.php`, `app/Models/Fatura.php`, `app/Filament/Resources/Faturas/Schemas/FaturaForm.php`,
  `app/Console/Commands/CorrigirFaturasQuitadasCommand.php`
- `tests/Feature/MatriculaExclusaoProtegidaTest.php`, `tests/Feature/ExclusaoMatriculaOutrasTelasTest.php`,
  `tests/Feature/FaturaCentavosTest.php`
