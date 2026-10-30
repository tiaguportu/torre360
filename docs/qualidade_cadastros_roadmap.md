# Qualidade dos Cadastros (Onda 81) — Parcialmente implementada

> **Status: parcial.** Está implementada **só a detecção de pessoas possivelmente duplicadas** (fila 5 do escopo; ver
> [Implementado](#implementado-em-2026-10-30-detecção-de-pessoas-duplicadas)). **Continuam propostos:** as demais filas na Minha Mesa,
> a correção guiada, a **mesclagem** de pessoas e o indicador de completude. Faz parte do [índice das ondas 80 a 88](ondas_80_a_88_indice.md).

## Implementado em 2026-10-30: detecção de pessoas duplicadas

- **Tela:** *Secretaria > Cadastros duplicados* (`App\Filament\Pages\PessoasDuplicadas`). Lista **pares** de cadastros, do mais provável ao
  menos, com os vínculos de cada um (matrículas, responsáveis, acesso, CRM) e o CPF parcialmente oculto. **Só aponta:** não une, não apaga e
  não altera cadastro. Abre com `ViewAny:Pessoa`; o link para o cadastro e o botão *Não é duplicata* exigem `Update:Pessoa` (sem permissão nova).
- **Serviço:** `PessoaDuplicadaDetectorService::pares()`. Critérios: (1) **CPF igual** (só dígitos; CPF de preenchimento como `000.000.000-00` e
  valor repartido por mais de 6 cadastros são ignorados); (2) **mesmo nome e mesmo nascimento** (nome sem acento, caixa e pontuação, com ao
  menos duas palavras); (3) **mesmo nome e mesmo e-mail ou telefone**, desde que os nascimentos não se contradigam e os CPFs não sejam diferentes.
- **O que nunca entra** (o "falso positivo é o inimigo da fila" da seção de riscos): contato igual **sem** o mesmo nome (a família
  compartilha), gêmeos (nomes diferentes), pai e filho com o mesmo nome (nascimentos diferentes), nome de uma palavra só, e responsável e
  aluno ligados diretamente em `aluno_responsavel`.
- **"Não é duplicata" lembrado:** tabela `pessoa_nao_duplicada` (par em ordem, único), migration `2026_10_30_100000`. O par marcado não volta.
- **Custo:** varre `pessoa` em lotes de 2.000, só com as colunas necessárias, e agrupa em PHP por valor normalizado (sem comparar todos com
  todos). O resultado fica 2 minutos em cache; *Recalcular* o refaz.
- **Limites conhecidos (decisão, não esquecimento):** o nome precisa ser **igual** depois de normalizado ("Maria da Silva" e "Maria Silva"
  não casam); contato igual com nomes parecidos não casa; a lista não tem fila na Minha Mesa nem aviso ao criar uma pessoa; e **não existe
  mesclagem**: quem confere corrige pelo cadastro de pessoa.
- **Testes:** `tests/Feature/PessoaDuplicadaTest.php`.

## Contexto

Quase tudo que a escola faz com dinheiro, lei ou comunicação depende de um cadastro certo: a cobrança precisa do CPF e do
e-mail do responsável financeiro; o Censo e a emissão de documentos pedem o dado completo do aluno; a régua e o aviso à família
precisam de um contato que funcione. Hoje o cadastro incompleto só aparece **no momento em que alguém tenta usá-lo** (a exportação
do Censo acusa, o contrato trava, a régua não encontra o e-mail). Falta olhar o cadastro todo, antes.

## O que já existe (conferido no código)

- `Pessoa::hasIncompleteCadastro()` e `getMissingCadastroFields()`: nome, nascimento, CPF, sexo, cor/raça, nacionalidade,
  naturalidade (se brasileiro) e endereço.
- `Matricula::getPessoasComCadastroIncompleto()`, `hasIncompleteCadastro()` e os escopos `comCadastroIncompleto()` /
  `comCadastroCompleto()`: aluno, responsáveis e responsáveis financeiros de uma matrícula. Servem de filtro na lista de matrículas.
- `App\Rules\Cpf` (validação do dígito verificador **na digitação**) e `pessoa.cpf` único e anulável.
- `LeadDuplicadoDetectorService` e `LeadMesclagemService`: detectam e mesclam **leads** duplicados (telefone, CPF, e-mail e
  aluno em comum). Para `Pessoa` existe agora só a **detecção** (`PessoaDuplicadaDetectorService`, ver acima); a mesclagem não existe.
- A página do **Censo Escolar** lista as matrículas com pendência para o arquivo da situação do aluno (Onda 58), mas só o que o Censo pede.
- A **Minha Mesa** (`FontePendencia`) e as filas de cadastro do CRM.

## Escopo proposto

### Filas na Minha Mesa (cada uma é uma classe pequena)
1. **Matrículas ativas com cadastro incompleto** (usa o escopo existente), com os campos que faltam.
2. **Contrato ativo sem responsável financeiro** ou com responsável financeiro sem CPF ou e-mail.
3. **CPF inválido** gravado (legado e importações, que não passaram pela regra de digitação).
4. **Responsável sem contato que funcione:** sem e-mail e sem telefone, ou com `aceita_comunicacao` desligado (a régua e o aviso
   não chegam).
5. **Pessoas possivelmente duplicadas** (mesmo nome normalizado e nascimento, ou mesmo telefone ou e-mail em pessoas sem vínculo).
6. **Aluno ativo sem responsável cadastrado.**

### Correção guiada
- Cada item leva à ficha com o campo faltante em destaque e, quando dá, a uma **ação de correção** (ex.: marcar CPF como
  "não possui", escolher o responsável financeiro entre os responsáveis já cadastrados).
- **Mesclar pessoas** reaproveitando o desenho do `LeadMesclagemService` (escolher a pessoa que fica, mover os vínculos, guardar um
  registro do que foi unido). É a parte arriscada: uma `Pessoa` está ligada a matrícula, responsáveis, contrato, usuário, funcionário
  e histórico. Fazer só com pré-visualização e sem desfazer automático.
- **Pedir a atualização à família** (link do Portal) quando a [Onda 80](requerimentos_online_familia_roadmap.md) existir.

### Indicador
Taxa de completude por turma e por unidade no [Painel da Direção](painel_direcao_roadmap.md): é o número que mostra se o esforço de
limpeza está funcionando.

## Base legal

LGPD, art. 6º, V (**qualidade dos dados**: exatidão, clareza e atualização) e art. 18, III (correção). O dado inexato é também
risco de cobrança e de comunicação indevidas (CDC, art. 42, parágrafo único: cobrança de quantia indevida).

## Dependências e riscos

- **Pequena e independente:** nenhuma dependência de outra onda; ajuda o Censo, a cobrança (Ondas 62 e 68), a régua e o aviso (Onda 70).
- **Falso positivo é o inimigo da fila.** Gêmeos têm o mesmo nascimento e, às vezes, o mesmo telefone e o mesmo responsável; a
  detecção de duplicados deve ser sugestão com "não é duplicata" lembrado, nunca mesclagem automática.
- Cada fila com cache curto (como as demais da Mesa) para não pesar a abertura da Mesa.
- Não corrigir nada em lote sem a conferência de uma pessoa.

## Fusão sugerida

Atualização cadastral pedida pela família (80) e correção a pedido do titular (34) chegam por canais diferentes ao mesmo lugar:
a ficha da pessoa. Esta onda é o lado da escola; as outras duas são o lado da família.
