# Copiloto Pedagógico IA: Plano de Aula e Adaptações Inclusivas (PEI)

Esta documentação descreve a arquitetura, as salvaguardas de privacidade (LGPD), a integração com a API do Google Gemini, a estrutura de permissões e as diretrizes operacionais do Copiloto Pedagógico no Torre360.

---

## 1. Visão Geral e Arquitetura

O Copiloto Pedagógico IA foi desenvolvido para apoiar o corpo docente em duas tarefas de alto impacto pedagógico:
1. **Estruturação de Planos de Aula Alinhados à BNCC:** Geração de objetivos com base na Taxonomia de Bloom Revisada, metodologias ativas, cronograma temporizado e instrumentos de avaliação formativa.
2. **Adaptações Curriculares e Plano Educacional Individualizado (PEI):** Formulação de estratégias didáticas adaptadas, recursos de acessibilidade e tecnologia assistiva para alunos com necessidades educacionais especiais ou transtornos de aprendizagem, fundamentadas no Desenho Universal para a Aprendizagem (DUA) e na Lei Brasileira de Inclusão (Lei nº 13.146/2015).

### Arquitetura de Comunicação e IA:
- **Centralização de Integração:** Todas as chamadas ao LLM utilizam o serviço `App\Services\GeminiAgentService::callGeminiApi()`, com cascata automática de modelos (`gemini-2.5-pro` → `gemini-2.5-flash`), orçamento estrito de execução, cabeçalhos de autorização seguros e sanitização de credenciais.
- **Saídas Estruturadas via Schema JSON:** Respostas com tipagem garantida via `responseSchema` da API Gemini, submetidas à validação pós-inferência por `Validator` do Laravel e reconciliação com o catálogo oficial da BNCC.
- **Isolamento de Configuração:** Opera sob `config/copiloto_pedagogico.php` com rate limit independente (`copiloto_pedagogico_user_{id}`). Não compartilha configurações nem tabelas com o módulo de CRM de Vendas.

---

## 2. Modelo de Dados e Tabelas

### `plano_aula_adaptacoes`
Tabela criada para persistir as adaptações curriculares geradas por aluno no contexto de um plano de aula:

| Coluna | Tipo | Detalhes |
|---|---|---|
| `id` | bigint unsigned | Chave primária auto-incremental. |
| `plano_aula_id` | bigint unsigned | FK para `plano_aula` (cascade). |
| `matricula_id` | bigint unsigned | FK para `matricula` (cascade). |
| `conteudo` | text / longtext | **Cast `encrypted:array`** (criptografia AES-256 em repouso). |
| `status` | string | Enum: `rascunho`, `revisado`, `aprovado`. |
| `gerado_por` | bigint unsigned | FK para `users` (quem solicitou a geração). |
| `revisado_por` | bigint unsigned | FK para `users` (nullable). |
| `revisado_em` | datetime | Data/hora da revisão pedagógica (nullable). |
| `aprovado_por` | bigint unsigned | FK para `users` (nullable). |
| `aprovado_em` | datetime | Data/hora da homologação da coordenação/AEE (nullable). |
| `modelo_ia` | string | Identificador do modelo LLM utilizado (ex.: `gemini-2.5-pro`). |

---

## 3. Fluxo de Dados e Minimização Estrita (LGPD)

### A. O que trafega para a API de IA (Google):
Para geração de **Planos de Aula**:
- Nome da turma e da série.
- Nome da disciplina.
- Número de aulas e duração total em minutos.
- Habilidades BNCC selecionadas contendo **código, nome e descrição oficial**.
- Instruções didáticas livres digitadas pelo professor (higienizadas contra PII por `ocultarDadosPessoais()`).

Para formulação de **Adaptações Curriculares (PEI)**:
- Nível de ensino e série (ex.: "Ensino Fundamental II - 7º Ano").
- Faixa etária aproximada deduzida da série (ex.: "12 a 13 anos").
- Nomes padronizados das categorias de necessidades especiais cadastradas (ex.: "Transtorno do Espectro Autista - Nível 1", "Baixa Visão").
- Nomes padronizados das categorias de transtornos de aprendizagem (ex.: "Dislexia").
- Nomes padronizados dos recursos de acessibilidade já concedidos (ex.: "Tempo estendido", "Fonte ampliada 24pt").
- Objetivos de aprendizagem e habilidades BNCC do plano de aula corrente.

### B. O que NUNCA é transmitido para a API externa:
- **Nenhum dado cadastral ou identificador direto do aluno:** Nome, data de nascimento, CPF, filiação, endereço ou telefone. O estudante é referenciado no prompt genericamente como *"o estudante"*.
- **Nenhum dado de saúde clínica:** Dados da `FichaMedica` (diagnósticos clínicos, medicamentos em uso, cartão SUS, alergias, restrições alimentares ou contatos de emergência).
- **Sem consolidação de múltiplos estudantes:** As gerações de PEI são estritamente individuais (uma chamada por matrícula), impedindo cruzamento ou agregação de perfis comportamentais pela IA.

---

## 4. Permissões de Acesso (Filament Shield)

As permissões são controladas pelo Filament Shield no formato `PascalCase` com dois-pontos:

| Permissão | Finalidade | Concessão Padrão |
|---|---|---|
| `GerarComCopiloto:PlanoAula` | Permite usar o assistente IA para preencher ou regenerar seções do Plano de Aula | `super_admin`, `admin`, `coordenador`, `professor` |
| `GerarPei:PlanoAula` | Permite solicitar a geração e homologação de adaptações PEI para um aluno com necessidades especiais | `super_admin`, `admin`, `coordenador` *(Professores requerem liberação manual)* |
| `ExportarPei:PlanoAula` | Permite gerar e baixar o PDF oficial confidencial do PEI do estudante | `super_admin`, `admin`, `coordenador` |

### Regeneração Modular de Seções (T7):
Além da geração completa do plano didático, o Copiloto permite a regeneração pontual e isolada de seções individuais:
- **Objetivos de Aprendizagem:** Mantém as habilidades BNCC selecionadas e recalcula os objetivos e níveis cognitivos da Taxonomia de Bloom.
- **Metodologia e Cronograma:** Regenera a metodologia ativa sugerida e a fragmentação do cronograma temporizado com papéis de professor e alunos.
- **Recursos Didáticos:** Atualiza a lista de materiais e recursos necessários.
- **Avaliação Formativa:** Reformula os instrumentos avaliativos e critérios de sucesso.
- **Instrução de Ajuste (Opcional):** Permite ao docente fornecer uma orientação curta (ex: "foco em trabalho em equipes", "recursos digitais").
- **Limite por Sessão:** Trava de segurança limitando a 10 regenerações por sessão para prevenção de abuso e rate limit.
- **Preenchimento sem Persistência:** A regeneração atualiza exclusivamente o campo correspondente no formulário sem gravar no banco de dados, preservando a autonomia docente de revisão antes do salvamento.

### Salvaguarda para Dados de Saúde e Adaptações Inclusivas (Policies):
Em conformidade com a auditoria de segurança (`docs/seguranca_pendencias.md`), as entidades de suporte à inclusão e dados sensíveis contam com Policies dedicadas:
- `App\Policies\PlanoAulaAdaptacaoPolicy`: Governa a visualização, geração, homologação e download de PDFs de adaptações PEI. Exige as permissões de Shield e aplica o **escopo do professor** (professores só acessam planos e estudantes de suas próprias turmas/disciplinas).
- `App\Policies\NecessidadeEducacaoEspecialPolicy` (`ViewAny:NecessidadeEducacaoEspecial`)
- `App\Policies\TranstornoAprendizagemPolicy` (`ViewAny:TranstornoAprendizagem`)
- `App\Policies\RecursoAcessibilidadePolicy` (`ViewAny:RecursoAcessibilidade`)

> **Impacto na operação (decisão de negócio pendente).** Essas três entidades aparecem como abas (Relation Managers) na edição de **Pessoa** (`PessoaResource`). Antes não havia Policy e qualquer usuário que abrisse a pessoa as via e editava (a lacuna descrita em `docs/lgpd_saude_policies.md`). Com as Policies, **somente `super_admin`** recebe `View/Create/Update/Delete`: `admin`, `coordenador` e `secretaria` deixam de ver essas abas até que um `super_admin` conceda as permissões em *Shield → Roles*. Isso é intencional (mesmo precedente de `FichaMedica`), mas quem hoje cadastra necessidades especiais precisa ser avisado e os papéis certos precisam receber a permissão. O PEI **não depende** dessas permissões: a geração lê as categorias pelo serviço, sob `GerarPei:PlanoAula` e o escopo do professor.

---

## 5. Trava Operacional: Flag de Habilitação do PEI

Por diretriz de privacidade do Torre360, a funcionalidade de adaptações PEI por IA nasce **desabilitada por padrão**:

```env
# .env
COPILOTO_PEDAGOGICO_PEI_HABILITADO=false
```

Quando a flag estiver `false`:
- O Relation Manager de "Adaptações Curriculares (PEI)" fica inacessível no painel.
- Chamadas via API ou serviço disparam imediatamente `PeiDesabilitadoException`.
- A habilitação em produção requer confirmação expressa de que a instituição possui contrato empresarial com o Google Workspace / Google Cloud Vertex AI com cláusula de **não retenção de dados para treinamento de modelos** (Zero Data Retention / Enterprise Privacy SLA).

---

## 6. Exportação em PDF e Diretrizes de Apresentação

- **Tarjas de Conformidade:** Enquanto o status da adaptação for `rascunho` ou `revisado`, o PDF é renderizado com marca d'água **"RASCUNHO - Gerado por IA, pendente de validação"**.
- **Aviso de Responsabilidade Médica e Pedagógica:** O documento exibe de forma compulsória o rodapé:
  > *"Documento de apoio pedagógico baseado nas diretrizes do DUA e Lei 13.146/2015. As sugestões geradas por inteligência artificial não constituem diagnóstico clínico, não prescrevem terapias ou medicamentos e não substituem o Plano de Atendimento Educacional Especializado (AEE)."*
- **Escapamento Anti-XSS (VULN-M4):** Todo texto proveniente da IA ou editado pelo docente é tratado como dado não confiável e renderizado exclusivamente com `e()` em Blade e DomPDF, nunca com interpretação crua `{!! !!}`.

---

## 7. Relatório de Impacto à Proteção de Dados (RIPD / LGPD)

> **Nota Técnica de Conformidade Legal e Regulatória**
> Aplicabilidade: Artigos 6º (Inciso III), 11, 14, 33–36 e 46 da Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018 - LGPD).

1. **Princípio da Necessidade e Minimização (Art. 6º, III):** O Copiloto Pedagógico trafega apenas as categorias taxonômicas de acessibilidade estritamente necessárias para a formulação da adaptação didática, descartando quaisquer identificadores diretos ou prontuários médicos.
2. **Tratamento de Dados Pessoais Sensíveis de Menores (Arts. 11 e 14):**
   - Os dados referem-se a crianças e adolescentes e envolvem necessidades de saúde/educação especial.
   - O tratamento é fundamentado no legítimo interesse e cumprimento de obrigação legal de promoção da educação inclusiva (Estatuto da Criança e do Adolescente e Lei Brasileira de Inclusão).
   - O armazenamento das adaptações é protegido por **criptografia ponta a ponta em repouso (AES-256)** via cast `encrypted:array` do Eloquent.
3. **Transferência Internacional de Dados (Arts. 33 a 36):**
   - O envio de dados para processamento no Google Gemini constitui transferência internacional de dados.
   - Para mitigar os riscos de tratamento secundário para aperfeiçoamento de inteligência artificial (Risco M9), o sistema condiciona o recurso à flag `COPILOTO_PEDAGOGICO_PEI_HABILITADO`, exigindo provisionamento corporativo com garantia contratual de privacidade.
4. **Segurança da Informação e Auditoria (Art. 46):**
   - Todas as operações de geração, revisão, aprovação e emissão de PDF de PEI geram registros no log unificado de auditoria (`activity_log` com canal `plano_aula`).
   - Os logs registram o autor, o momento e a matrícula do aluno, sem expor o conteúdo sensível da adaptação no histórico geral.
