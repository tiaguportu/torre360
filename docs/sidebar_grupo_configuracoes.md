# Barra Lateral: Tabelas Auxiliares Agrupadas em "Configurações"

Painel: `App\Providers\Filament\AdminPanelProvider.php` (Filament v5)

Muitos Resources são apenas tabelas de apoio/lookup (Código BACEN, Bancos,
Cidades, Estados, Países, tipos e categorias diversas), mas viviam misturados
dentro de grupos de negócio (Financeiro, Localização e Cadastros, Acadêmico,
Avaliações, Secretaria, etc.), poluindo a sidebar e dificultando encontrar
tanto os itens de negócio quanto os de configuração.

Esses 19 Resources foram movidos para o grupo `Configurações` (já existente
no provider, `->collapsed()` por padrão, antes usado só pelo
`SchoolSetupWizard`):

| Resource | Grupo anterior |
|---|---|
| País | Localização e Cadastros |
| Estado | Localização e Cadastros |
| Cidade | Localização e Cadastros |
| Tipo de Vínculo | Localização e Cadastros |
| Banco | Financeiro |
| Código BACEN | Financeiro |
| Centro de Custo | Financeiro |
| Plano de Contas | Financeiro |
| Tributação do Curso | Financeiro |
| Tipo de Documento | Secretaria |
| Tipo de Ocorrência | Convivência e Disciplina |
| Categoria de OS | Operacional |
| Categoria de Avaliação | Avaliações |
| Etapa Avaliativa | Avaliações |
| Área de Conhecimento | Acadêmico |
| Campo de Experiência | Currículo (BNCC) |
| Turno | Calendário e Horários |
| Setor de Atendimento | Comunicação Escolar |
| Configuração (chave/valor do sistema) | Sistema e Segurança |

**Ficaram de fora de propósito** (são entidades de negócio ou registros
transacionais, não lookups estáticos): Instituição de Ensino, Endereço e
Unidade (permanecem em Localização e Cadastros); Dia Não Letivo (permanece em
Calendário e Horários, por ser um registro de datas específicas, não uma
tabela de configuração).

## Mecanismo

Cada Resource define seu grupo via propriedade estática:

```php
protected static string|\UnitEnum|null $navigationGroup = 'Configurações';
```

`$navigationSort` foi renormalizado (1 a 19) em cada um dos Resources movidos
para refletir uma ordem lógica dentro do novo grupo, já que os valores antigos
foram calibrados para a posição de cada item no grupo de origem. Nenhuma
alteração foi necessária em `AdminPanelProvider.php` — o grupo `Configurações`
já estava declarado e colapsado.
