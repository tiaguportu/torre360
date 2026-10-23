# Acesso do Contador (Onda 75) — Proposta para o Futuro

> **Status: não implementado.** Este documento registra o escopo planejado para quando a implementação for autorizada.
> Não há código, migration ou teste desta funcionalidade. Fecha o ciclo da
> [Exportação para a Contabilidade (Onda 73)](exportacao_contabil_roadmap.md).

## Contexto

A Onda 73 entrega o arquivo ao escritório de contabilidade, mas quem o gera e envia é a administração da escola: o
arquivo sai do sistema por e-mail ou mensageiro, sem controle de quem o abriu, e o escritório pede de novo a cada correção.
O contador é uma **pessoa de fora** que precisa de um acesso **mínimo e temporário**: baixar o que foi exportado, ver o
histórico de lotes e conferir o balancete.

Dar a ele um papel de equipe comum (secretaria, por exemplo) abriria dados de alunos e de famílias que ele não precisa
ver. Hoje não existe papel para quem não é da escola nem da família.

## O que já existe

- A Onda 73: `View:ExportacaoContabil` e `Exportar:ExportacaoContabil`, lotes com SHA-256 e download por rota própria.
- Papéis por permissão (Filament Shield) e **datas de ativação e desativação** na conta (`users.activated_at` e
  `deactivated_at`), o que permite um acesso com prazo sem criar mecanismo novo.
- Log de atividades e `AuditMiddleware`.
- O convite de usuário com e-mail e o painel em que o usuário escolhe o papel ativo.

## Escopo proposto

1. **Papel `contador`** que só enxerga o grupo *Financeiro* → *Contabilidade* (conferência, parâmetros em modo leitura,
   histórico e download) e *Meus Relatórios* das fontes financeiras. Nenhum acesso a aluno, matrícula ou documento.
2. **Acesso com prazo**: a conta nasce com `deactivated_at` (por exemplo, 90 dias) e a administração renova.
3. **Registro de quem baixou o quê**: cada download vira linha do histórico do lote (quem, quando, IP), visível à escola.
4. **Aviso ao contador** quando sai um lote novo (a notificação já existente da Onda 72, com o link para o sistema).
5. **Comentário no lote**: o escritório devolve uma observação ("conta X sem código") que aparece na conferência.

## Base legal

LGPD, art. 6º, III (necessidade) e art. 46 (medidas de segurança): o contador recebe dado financeiro da escola e, por isso,
só o que o fechamento pede. Não substitui o contrato de prestação de serviço e a cláusula de confidencialidade com o
escritório.

## Dependências e riscos

- **MFA (Onda 46)** é pré-requisito prático: conta externa com acesso a dado financeiro sem segundo fator é o ponto mais
  fraco do sistema. Sem MFA, não liberar.
- O papel precisa de teste que prove o que ele **não** abre (cada recurso e página do painel). O `Gate::before` do super
  admin não se aplica ao papel, mas as telas devem conferir o estado do registro.
- Ordem: 46 (MFA) → 75.
