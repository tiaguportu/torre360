# Censo Escolar: Situação do Aluno (Onda 58)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda da tela **Censo Escolar (2ª etapa)**
> (`/admin/secretaria/censo-escolar`, grupo Secretaria) e na seção 72 do `MANUAL_USUARIO.md`.

## Por que é uma onda nova e não uma mescla

O sistema já exportava a **matrícula inicial** (1ª etapa) do Censo Escolar — registros 00, 10, 20, 30, 40, 50
e 60, em `app/Services/Educacenso/` —, mas isso foi feito antes do levantamento por ondas e **não pertence
a nenhuma onda numerada**. A 2ª etapa, a **Situação do Aluno**, não existia. Entrou como Onda 58,
completando aquele exportador sem alterá-lo.

## Contexto

O Censo Escolar tem duas etapas: a **matrícula inicial** (a fotografia da escola na data de referência, a
última quarta-feira de maio) e a **Situação do Aluno** (o que aconteceu com cada aluno até o fim do ano:
aprovado, reprovado, transferido, deixou de frequentar...). A escola informa pelos formulários do Educacenso
ou por **migração** de um arquivo `.txt` no leiaute do Inep. O processo oficial tem estes passos:

1. Exportar do Educacenso o arquivo da matrícula inicial para obter os códigos do Inep.
2. Atualizar o sistema da escola com esses códigos.
3. Pedir a identificação dos alunos que ficaram sem código (os admitidos depois).
4. Importar o resultado da identificação.
5. Gerar o arquivo da Situação do Aluno (registros 89, 90 e 91) e importá-lo.
6. Encerrar o ano escolar no Educacenso.

## O que já existia

- O exportador da 1ª etapa, o código Inep da instituição e da unidade, a etapa de ensino do Educacenso na
  turma (`EtapaEnsino`), o resultado final por disciplina (`SituacaoFinalDisciplina`, com exame final), as
  transferências (`TransferenciaEscolar`) e a situação da matrícula.
- Os exportadores já liam `codigo_inep` da pessoa e da turma, que **não existiam** no banco: os campos
  saíam vazios no arquivo.
- **Faltava:** guardar o que o Inep devolve, apurar a situação e gerar os arquivos.

## O que foi feito

### Dados
- `pessoa.codigo_inep` (ID Inep, 12 dígitos), `turma.codigo_inep` (até 10) e
  `matricula.codigo_matricula_inep` (até 12). Passam a sair também nos registros 30, 20 e 60 da 1ª etapa.
- `censo_situacoes_aluno`: ajuste manual da situação (um por matrícula), com **justificativa cifrada** e
  quem ajustou. O log guarda a situação, nunca a justificativa.
- Permissão `GerenciarCensoEscolar:Matricula` (administração e secretaria).

### Apuração da situação (`SituacaoAlunoCensoService`)
Ordem de decisão:

1. **Ajuste manual** da secretaria (vale no lugar do cálculo, mostra o cálculo como aviso).
2. **Transferência de saída concluída** → 1, transferido.
3. **Evasão** → 2, deixou de frequentar. Matrícula **cancelada** ou **trancada** sem transferência também
   vira 2, com aviso para conferir.
4. **Educação infantil** (etapas 1, 2 e 3) → 7, sem movimentação.
5. **Resultado final** por disciplina (considera o exame final): alguma reprovada → 4; todas aprovadas → 5,
   ou 6 (aprovado concluinte) na etapa final do curso; disciplina em recuperação ou sem resultado →
   pendência, nunca um chute.

Códigos do leiaute v3 do Censo 2025: 1 transferido, 2 deixou de frequentar, 3 falecido, 4 reprovado,
5 aprovado, 6 aprovado concluinte, 7 sem movimentação, 8 em andamento. As regras por etapa do leiaute são
validadas (por exemplo, "aprovado concluinte" só nas etapas finais; "em andamento" só em EJA e cursos
modulares). Falecimento e "em andamento" o sistema não sabe: entram por ajuste manual.

### Registros
- **90:** matrícula que já tem o código da matrícula do Inep (veio da matrícula inicial).
- **91:** matrícula sem código, iniciada **depois** da data de referência (admitido após).
- Matrícula sem código e iniciada antes da referência: **pendência** ("importe o retorno da matrícula
  inicial"). Quem saiu antes da referência sem nunca ter recebido código não estava no Censo e não entra.

### Arquivos
- **Situação do Aluno:** `89|escola|CPF do gestor|nome do gestor`, depois os `90` (8 campos) e os `91`
  (10 campos). ISO-8859-1, `|` como separador, CRLF, maiúsculas e sem acento. Só entram as matrículas sem
  pendência; as demais aparecem com o motivo.
- **Identificação** (para quem não tem ID Inep): 9 campos (código na escola, CPF, certidão, nome, nascimento,
  filiação 1 e 2, município de nascimento pelo código IBGE). Quem não tem nome, nascimento e município de
  nascimento fica de fora, com o motivo.
- Nomes de arquivo `SA_<inep>_<ano>.txt` e `ID_<inep>_<ano>.txt`, dentro do limite de 20 caracteres do Inep.

### Retorno do Educacenso (`EducacensoRetornoImporter`)
- Lê a exportação da matrícula inicial (registros 20, 30 e 60) e o `RESULTADO_CERTO` da identificação.
- Casa pelos códigos próprios que o nosso exportador enviou (código da turma, id da pessoa) e **só grava
  em campo vazio**: um código diferente do cadastrado, ou um ID Inep já usado por outro aluno, vira
  **divergência** para a secretaria conferir. Escolas do arquivo sem cadastro aqui são ignoradas.
- Tela com edição manual dos três códigos (para o `RESULTADO_PROVAVEIS`, que o Inep manda conferir).

## Decisões e limites

- **Inconsistência no leiaute v3:** a regra 3 do campo 8 do registro 90 ainda diz que, na educação infantil,
  só valem 1, 2, 3 e **8**; as regras 5 e 6 (e a troca de códigos da v3) dizem que 8 é só para EJA/curso
  modular e que 7, sem movimentação, é só para as etapas 1 e 2. Seguimos as regras 5 e 6. Se o validador do
  Educacenso recusar, a secretaria ajusta manualmente e a regra é revista.
- **Importação sobrepõe tudo:** o Inep avisa que importar apaga o que a escola informou antes (inclusive à
  mão) e que não dá para enviar em partes. A Ajuda da tela repete isso. Matrícula que ficar de fora entra
  como "importado parcialmente" e deve ser informada direto nos formulários do Educacenso.
- **Não feito:** turmas com mais de uma etapa no registro 91 (o Inep pede a etapa do aluno; vira pendência
  para informar no Educacenso), a "Tabela de Regra de Admissão" e as etapas equivalentes (documentos
  auxiliares do Inep não lidos), a troca do gestor responsável pela Situação do Aluno (feita no Educacenso)
  e o encerramento do ano escolar.
- A data de referência pode ser fixada por ano em `config/educacenso.php`.

## Fontes oficiais (Inep)

- Layout de Importação e Exportação 2025 (Situação do Aluno), versão 3, e Layout de Identificação 2025.
- Etapas e Instruções Gerais para a Migração (2ª etapa, 18/02/2026), na página de migração da Situação do
  Aluno em `gov.br/inep`. Arquivos em `download.inep.gov.br/educacao_basica/educacenso/migracao/2025/`.
- O leiaute muda a cada ano: conferir a versão do ano antes de gerar o arquivo.

## Testes

`tests/Feature/CensoSituacaoAlunoTest.php` (apuração, ajuste, arquivos, importação) e
`tests/Feature/CensoEscolarTelaTest.php` (acesso, isolamento entre escolas, ações e downloads).
