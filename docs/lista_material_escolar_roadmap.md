# Lista de Material Escolar (Onda 59)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda das telas e na seção 73 do
> `MANUAL_USUARIO.md`. Tela da secretaria: `/admin/lista-material-escolars` (grupo Secretaria). Portal da
> Família: **Lista de Material**, em Meus Dados.

## Contexto

Toda escola entrega às famílias a lista de material do ano, e é uma das maiores fontes de reclamação no
Procon: pedir material de limpeza, papel higiênico ou toner; exigir marca, loja ou kit fechado. A **Lei nº
12.886/2013** incluiu o § 7º no art. 1º da Lei nº 9.870/1999: é **nula** a cláusula que obrigue o
contratante ao pagamento adicional ou ao fornecimento de qualquer material escolar de **uso coletivo** dos
estudantes ou da instituição, necessário à prestação dos serviços contratados; o custo deve estar na
anuidade. Exigir marca ou local de compra é venda casada (CDC, art. 39).

## O que já existia

- Série, período letivo e turma; o Portal da Família com seleção de aluno; o padrão de PDF (dompdf) e de
  permissões por papel.
- Nada de lista de material (nem em planilha dentro do sistema).

## O que foi feito

### Dados
- `listas_material_escolar`: uma lista por **série e período letivo** (índice único), com situação
  (rascunho ou publicada), recado às famílias, quem publicou e quando.
- `itens_lista_material`: descrição, quantidade, unidade, categoria (papelaria, livros e apostilas, arte,
  higiene pessoal do aluno, educação física, outros), observação, confirmação de uso individual e ordem.
- **Sem campo de marca nem de loja**, de propósito.
- Permissões `ViewAny/View/Create/Update/Delete/Publicar:ListaMaterialEscolar`: a secretaria monta e publica;
  a coordenação consulta. A família vê pelo vínculo com o aluno.

### A trava (`ListaMaterialService`)
Cada item é avaliado pela descrição (sem acento e sem diferença de maiúsculas, no começo da palavra):

- **Bloqueado:** uso coletivo evidente (papel higiênico, papel toalha, sabão, detergente, desinfetante,
  material e produtos de limpeza, saco de lixo, copo descartável, água mineral, giz, apagador, pincel e
  marcador de quadro, toner, cartucho, grampeador, fósforo, álcool). **Não há como incluir**, nem
  confirmando. Exceção: "giz de cera" e "giz pastel".
- **Atenção:** pode ser individual ou coletivo (resma, papel sulfite/A4, fita adesiva, clipes, grampo,
  percevejo, barbante, algodão, verniz, sabonete, lenço umedecido, pilha) **ou quantidade de 30 ou mais**.
  Só entra com a **confirmação de que é de uso individual do aluno e em quantidade coerente**; a confirmação
  fica gravada no item.
- **Livre:** o resto.

Os termos e o limite de quantidade ficam em `config/material_escolar.php`. É um apoio a quem monta, não uma
decisão jurídica: a Ajuda diz isso e manda conferir com o jurídico. A exceção "giz de cera" mostra o risco do
método (falso positivo); a lista de exceções existe para isso.

### Publicação
- Publicar **revalida** todos os itens (a configuração pode ter mudado desde que foram salvos) e exige ao
  menos um item. Grava data e usuário e registra no log (só a contagem de itens).
- Lista publicada **não muda nem se apaga** (nem para o `super_admin`); volta para rascunho com "Voltar
  para rascunho", e as famílias deixam de vê-la até nova publicação. A tela de edição continua abrindo
  depois de publicada, justamente para isso.
- **Copiar de outro período:** copia os itens para uma lista vazia e deixa de fora, com o motivo, o que a
  trava atual não aceita.

### Para a família
- Página **Lista de Material** no Portal, por aluno, agrupada por categoria, com o recado da escola, a data
  de publicação e o PDF. O aviso legal é fixo: a escola não exige marca, loja nem kit; material de uso
  coletivo é custeado pela mensalidade.
- PDF (`/lista-material/{id}`): a família abre só a lista **publicada** da série e do período de um aluno
  seu; a equipe com `View:ListaMaterialEscolar` abre qualquer uma (rascunho vem marcado como rascunho).

### Reajuste de mensalidades (Onda 44)
- O cenário de reajuste ganhou um ponto de atenção: séries do cenário sem lista publicada no período de
  destino da campanha. As famílias costumam pedir a lista junto com o valor do ano.

## Decisões e limites

- Não controla preço, fornecedor, estoque nem pedido de compra; não há kit.
- Apostila vendida pela própria escola é uma decisão comercial fora do sistema: a família não pode ser
  obrigada a comprar da escola.
- Educação infantil: itens de higiene pessoal do aluno (escova, creme dental, toalha) são aceitos; álcool em
  gel e produtos de limpeza, não.

## Testes

`tests/Feature/ListaMaterialEscolarTest.php` (trava, publicação, cópia, família e PDF),
`tests/Feature/ListaMaterialTelasTest.php` (listagem, criação, edição, itens e Portal) e o ponto de atenção
em `tests/Feature/ReajusteMensalidadeTest.php`.
