# Transporte Escolar (Onda 12) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a
> implementação for autorizada — não há código, migration ou teste desta funcionalidade no
> sistema ainda.

## Contexto

Módulo de logística (rotas, veículos, motoristas e vínculo aluno↔rota), não coberto pela
lista de marketing do Sponte nem pelas Ondas 1-8.

## O que já existe (e não é isto)

Existe hoje uma **declaração impressa** de transporte — `TemplateDocumento` do tipo
`declaracao_transporte` (`App\Enums\TipoTemplateDocumento::DeclaracaoTransporte`), seedada
em `database/seeders/TemplateDocumentosSeeder.php`: um documento que atesta endereço,
matrícula e horário de aulas para fins de concessão de passe escolar/gratuidade no
transporte público. Esse documento **não tem nenhum campo de rota, veículo, motorista ou
vínculo aluno-rota** — é só uma declaração para um órgão externo (prefeitura/concessionária
de transporte público), não a gestão do transporte da própria escola (van/ônibus
escolar próprio ou terceirizado). As duas coisas são independentes; esta onda não altera a
declaração já existente.

## Escopo

### Modelos novos
- `RotaTransporte`: nome, turno, veículo, motorista.
- `Veiculo`: placa, modelo, capacidade.
- `Motorista`: pode reaproveitar `Pessoa` (vínculo próprio) ou `Fornecedor` (motorista
  terceirizado) — decisão de modelagem a tomar quando a onda for autorizada.
- Vínculo aluno↔rota: pivot `Matricula`×`RotaTransporte` com ponto de embarque/
  desembarque.

### Telas
- Cadastro de rotas, veículos e motoristas.
- Vínculo de alunos a rotas (pela secretaria).
- Relatório "quem embarca em qual rota" por turno.

## Dependências já satisfeitas

- Nenhuma — módulo independente das demais ondas.
