# Documentos da Escola com Validade (Onda 60) — Proposta para o Futuro

> **Status: proposta.** Não há código, migration ou teste desta onda.

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
