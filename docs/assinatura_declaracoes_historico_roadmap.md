# Declarações e Histórico com Assinatura Digital (Onda 74)

> **Status: implementado.** Detalhes de uso ficam no texto de Ajuda das telas e na seção 89 do `MANUAL_USUARIO.md`.
> Telas: **Documentos Emitidos (QR)** e **Histórico Escolar Multi-Ano** (botões novos nas linhas) e **Documentos para Assinatura**
> (nível da assinatura). Estende a [Onda 69](assinatura_eletronica_documentos_roadmap.md).

## Contexto

A Onda 69 levou documentos que nascem de **modelos** (distrato, contrato de trabalho) para a assinatura eletrônica. Dois
documentos oficiais da escola ficaram de fora porque já nascem como PDF, com QR Code de autenticidade:

- a **declaração emitida** (matrícula, frequência, quitação, transferência...), gerada por `DocumentoService::gerarPdf()`;
- o **histórico escolar**, gerado na hora por `HistoricoEscolarService::gerarPdf()`.

Eles saíam sem assinatura nenhuma: valiam pelo QR Code. Escolas e redes costumam exigir a assinatura da direção ou da
secretaria, e parte delas exige certificado digital ICP-Brasil. Esta onda dá aos dois documentos uma **cópia assinada**.

## O que já existia

- `AssinaturaDocumentoService` (envio ao Assinafy, retorno por webhook e rotina, certificado guardado, reação do
  registro ligado ao documento) e o `DocumentoAssinatura`, com `documentavel` polimórfico.
- `VisualizarDocumentoController` abre os PDFs de `documentos_assinatura/` para quem pode ver o documento (equipe com
  permissão, quem foi chamado para assinar e a família do aluno).
- Página pública de verificação (`/validar-documento/{código}`) para declaração e histórico.

## O que foi feito

### Dois caminhos para a mesma cópia assinada

1. **Assinatura pelo Assinafy.** Botão **Enviar para assinatura**: o **PDF que a escola emitiu** (o mesmo que a família
   baixa) vira um documento da Onda 69, sem texto de modelo, com a direção da unidade (representantes legais com e-mail)
   sugerida como assinante. A tela do documento envia, acompanha e baixa o certificado como nos demais.
2. **PDF assinado fora do sistema.** Botão **Anexar PDF assinado**: a secretaria assina o PDF com o certificado digital da
   escola (ICP-Brasil A1 ou A3) ou no gov.br e anexa o arquivo. É o caminho de quem precisa de **assinatura qualificada**.

Nos dois, ao fim o documento oficial guarda o **caminho da cópia assinada, quando foi assinada e o nível** (campos
`arquivo_assinado_path`, `assinado_em` e `assinatura_nivel` em `solicitacao_documentos` e `historico_escolars`).

### O que o sistema lê no PDF (e o que não faz)

`AssinaturaPdfInspector` **lê** o arquivo: quantas assinaturas digitais ele traz (dicionários `/Type /Sig` com
`/ByteRange`), o formato (`/SubFilter`, por exemplo PAdES) e se o certificado embutido tem marca da ICP-Brasil (o nome
"ICP-Brasil", a "Autoridade Certificadora Raiz Brasileira" ou o OID 2.16.76.1). Daí sai o nível:

| Nível | Quando |
|---|---|
| Assinatura qualificada (certificado ICP-Brasil) | O PDF traz assinatura digital com marca da ICP-Brasil |
| Assinatura digital (certificado não ICP-Brasil) | O PDF traz assinatura digital sem a marca |
| Assinatura eletrônica | PDF certificado do Assinafy sem assinatura digital embutida: a evidência é a trilha do próprio Assinafy |

O inspetor **não valida**: não confere a integridade do conteúdo assinado, a cadeia de certificação, a revogação nem a
validade do certificado. Isso é do **Verificador de Conformidade do ITI** (validar.iti.gov.br) ou do validador do próprio
assinador. A tela do documento, a notificação e a página pública dizem isso em palavras, e o registro guarda
`validado: false`. Um nível "qualificada" aqui significa "o PDF diz que tem", não "o ITI conferiu".

Para a **declaração**, o sistema também compara o começo do arquivo assinado com o PDF emitido (as ferramentas de
assinatura costumam acrescentar a assinatura ao fim do arquivo). Se não bate, aceita e **avisa** na tela do documento: a
ferramenta pode ter regravado o arquivo, ou o PDF pode ser de outro documento. O histórico é gerado na hora e não tem PDF
guardado, então não há comparação. O anexo exige marcar que **conferiu que o PDF é o documento do aluno**.

### Regras que protegem o documento assinado

- Uma declaração com cópia assinada **não pode ser emitida de novo** (`DocumentoService::gerarPdf` recusa) e perde os
  botões de editar e excluir da lista; o histórico assinado perde os de sincronizar e excluir. A cópia assinada é do PDF
  que existia na hora; refazer o PDF a deixaria sem par.
- Uma segunda cópia assinada também é recusada (a assinatura em andamento precisa ser cancelada antes).
- Só **um** documento em andamento por registro. Declaração precisa estar *Disponível* e ter o PDF emitido no servidor.
- Arquivo que não é PDF, sem assinatura digital ou acima de **15 MB** é recusado.

### Telas

- **Documentos Emitidos (QR) e Histórico Escolar Multi-Ano:** coluna *Assinatura digital*; ações *Enviar para assinatura*, *Anexar PDF
  assinado*, *Baixar cópia assinada* e *Ver assinatura* (cada uma só aparece quando cabe).
- **Documento para assinatura:** seção *Assinatura digital* (nível, origem, quantidade, formato, se parte do PDF emitido,
  SHA-256 do arquivo assinado); documento assinado fora não mostra a lista de assinantes.
- **Portal da família:** *Baixar com assinatura digital* nas declarações e, ao lado do histórico oficial, *Histórico com
  assinatura digital*.
- **Página pública:** bloco *Cópia com assinatura digital* com a data e o nível, orientando a pedir o arquivo à secretaria e
  conferir no ITI. O arquivo não é servido ao público.

### Permissões

| Permissão | Quem | Para que |
|---|---|---|
| `AnexarAssinado:DocumentoAssinatura` (nova) | super admin, administração e secretaria | Afirmar a autenticidade do documento oficial com um PDF assinado fora |
| `Create:DocumentoAssinatura` (Onda 69) | idem | Montar o documento para o Assinafy |

A família abre a cópia assinada pelo mesmo caminho do documento: pela matrícula (declaração) ou pelas pessoas ligadas ao
usuário (histórico).

### Dados e privacidade

PDFs em `documentos_assinatura/{id}/` (área privada). O log registra o nível e a quantidade de assinaturas, nunca o
conteúdo. O resultado da leitura fica em `documentos_assinatura.nivel_assinatura` e `assinatura_detalhes`.

## Decisões e limites

- **Não guardar certificado A1 (PFX) no servidor para assinar sozinho.** Seria o jeito de assinar em lote, mas faz do
  servidor um alvo: quem o invade assina em nome da escola. A assinatura continua nas mãos de quem tem o certificado.
- **Não validar a cadeia aqui.** Validar com segurança exige as cadeias de certificação e a verificação de revogação
  atualizadas; é melhor apontar para o validador oficial do que prometer o que o sistema não confere.
- **Assinatura qualificada não é exigida pelo sistema.** Quem exige é o sistema de ensino da escola; o sistema só abre o
  caminho e registra. Confirme com o órgão da sua rede se o histórico precisa de certificado ICP-Brasil.
- **Base legal:** Lei nº 14.063/2020 (assinatura eletrônica simples, avançada e qualificada) e Medida Provisória nº
  2.200-2/2001 (ICP-Brasil).

## Testes

`tests/Feature/AssinaturaOficialTest.php` (29): leitura do PDF (sem assinatura, digital, ICP-Brasil, arquivo que não é
PDF), anexo da declaração e do histórico, recusas (sem assinatura, tamanho, já assinado, em andamento, sem PDF emitido),
caminho do Assinafy com e sem assinatura embutida no certificado, acesso da família e de estranhos, página pública e as
telas.

## Próximos passos (apenas documentados)

- **Validação técnica** por serviço externo (ITI ou provedor), registrando o resultado e a data da conferência.
- **Carimbo do tempo** (ICP-Brasil) como segunda evidência da data.
- **Assinatura em lote** de históricos de uma turma, pelo Assinafy, com a direção assinando uma vez.
- **Certificados de conclusão** e outros documentos que nascem como PDF entram pelo mesmo serviço
  (`DocumentosOficiaisService`), com uma entrada no tipo de documento.
