# Documentos da Escola com Validade (Onda 60)

> **Status: Implementada em 2026-10-23.** Migration e model `DocumentoRegulatorio` (tipos regulatórios, unidade, prazos de validade, antecedência de alerta, responsável e arquivo privado), service `DocumentoRegulatorioService` (cálculo de status e sincronização), Filament Resource com action `ajuda`, visualização em cards mobile com `->stackedOnMobile()` e políticas Shield. Testado em `DocumentoRegulatorioTest`.

## Contexto

A escola funciona com documentos que vencem: alvará de funcionamento, AVCB/CLCB do Corpo de Bombeiros,
licença sanitária, autorização do órgão de ensino, laudos de extintores, elétrica, para-raios e
reservatório, controle de pragas e brinquedos do parque. Vencimento perdido pode fechar a escola ou
inviabilizar o seguro. Hoje cada um fica numa pasta ou na memória de alguém.

## O que já existe

- `InstituicaoEnsino` e `Unidade`: CNPJ, código Inep e situação de funcionamento.
- Manutenção preventiva (Onda 39): planos recorrentes e ordens de serviço, com a OS de manutenção dos
  equipamentos.
- O molde do painel de vencimentos e do aviso semanal (férias, Onda 10).
- **Falta:** cadastro dos documentos regulatórios, com validade, arquivo e alerta.

## Escopo

### Dados
- `DocumentoRegulatorio`: unidade, tipo (lista editável, com sugestões acima), número, órgão emissor,
  emissão, **validade**, antecedência do alerta, responsável pela renovação, arquivo em disco privado e
  observação.
- Histórico: cada renovação é um novo registro do mesmo tipo; o anterior fica como vencido/substituído.

### Telas
- Painel **Documentos da escola**: vencidos, vencendo, sem documento do tipo exigido para a unidade.
- Aviso semanal à administração só com contagens e o nome do documento (sem o arquivo).
- Ação "Abrir ordem de serviço de renovação" ligada à Onda 39 (quando o documento exige vistoria ou laudo).
- Checklist por tipo de unidade, para o cadastro de uma filial nova não esquecer nada.

### Ampliação proposta: contratos com vencimento
O mesmo painel pode guardar os **contratos da escola que vencem**: seguro patrimonial e de responsabilidade
civil, manutenção de elevador, extintores e ar-condicionado, vigilância e transporte, com a vigência, o valor, o
índice de reajuste e a data limite para avisar o fornecedor de que não vai renovar. É o mesmo cadastro (tipo,
vigência, arquivo privado, alerta), com o fornecedor ligado ao cadastro da Onda 38 (compras). A diferença é o
alerta duplo: o do vencimento e o da data de aviso prévio, que costuma vir antes e é a que faz a escola pagar
mais um ano por distração.

## Regras e limites

- Exigências, prazos de validade e nomes dos documentos variam por município e por estado (por exemplo, o
  AVCB é do Corpo de Bombeiros estadual): **nenhum prazo fixo no código**; a validade é a do documento.
- Arquivos privados; acesso pela administração; log do envio e da exclusão.

## Dependências

- Nenhuma obrigatória. A Onda 39 ajuda a gerar a OS de renovação.

## Riscos e observações

- O sistema avisa do vencimento; a renovação depende de processo externo, que pode demorar semanas: o
  alerta precisa de antecedência generosa por tipo.

## Esforço

Pequeno.

## Fusão sugerida

Faz parte do conjunto **"Vencimentos"** com a [Onda 56](saude_seguranca_trabalhador_roadmap.md) (exames e Lei Lucas) e o painel de férias (Onda 10, implementado). Veja o racional na Onda 56. A ampliação que inclui contratos de seguro e de manutenção com data de aviso prévio é o mesmo cadastro com outro tipo.

Veja o [índice das ondas 68 a 71](ondas_68_a_71_indice.md#fusões-sugeridas-para-dar-completude).
