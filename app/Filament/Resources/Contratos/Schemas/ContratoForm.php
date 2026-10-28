<?php

namespace App\Filament\Resources\Contratos\Schemas;

use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TemplateContrato;
use App\Services\EncargosAtrasoService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ContratoForm
{
    public static function rotuloMatricula(Matricula $matricula): string
    {
        $turma = $matricula->turma?->nome ?? 'Sem Turma';
        $aluno = $matricula->pessoa?->nome ?? 'Sem nome';

        return "{$turma} - {$aluno}";
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Situação da Assinatura Digital')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (?Contrato $record): bool => $record !== null && $record->exists)
                    ->schema([
                        ViewField::make('painel_assinatura')
                            ->view('filament.resources.contratos.painel-assinatura')
                            ->viewData(fn (?Contrato $record): array => ['record' => $record])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Matrícula e modelo')
                    ->description('Vínculo acadêmico e modelo de contrato que rege os termos.')
                    ->schema([
                        Select::make('template_contrato_id')
                            ->label('Template do Contrato')
                            ->relationship('templateContrato', 'nome')
                            ->default(fn () => TemplateContrato::where('is_padrao', true)->first()?->id)
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        Select::make('matricula_id')
                            ->relationship(
                                name: 'matricula',
                                titleAttribute: 'id',
                                modifyQueryUsing: fn (Builder $query) => $query->with(['pessoa', 'turma']),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Matricula $record): string => static::rotuloMatricula($record))
                            ->getSearchResultsUsing(function (string $search): array {
                                return Matricula::query()
                                    ->with(['pessoa', 'turma'])
                                    ->whereHas('pessoa', fn ($query) => $query->where('nome', 'like', "%{$search}%"))
                                    ->orWhereHas('turma', fn ($query) => $query->where('nome', 'like', "%{$search}%"))
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Matricula $record) => [$record->id => static::rotuloMatricula($record)])
                                    ->toArray();
                            })
                            ->getOptionLabelsUsing(function (array $values): array {
                                return Matricula::query()
                                    ->with(['pessoa', 'turma'])
                                    ->whereIn('id', $values)
                                    ->get()
                                    ->mapWithKeys(fn (Matricula $record) => [$record->id => static::rotuloMatricula($record)])
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Aluno (Matrícula)')
                            ->columnSpanFull(),
                    ]),

                Section::make('Valores e encargos')
                    ->description('Definição do valor anual/total e percentuais aplicáveis em caso de atraso.')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                TextInput::make('valor_total')
                                    ->label('Valor Total')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->required(),

                                TextInput::make('multa_percentual')
                                    ->label('Multa por atraso (%)')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->maxValue(EncargosAtrasoService::MULTA_MAXIMA_LEGAL)
                                    ->suffix('%')
                                    ->placeholder(fn (): string => number_format((float) config('financeiro.encargos.multa_percentual', 2), 2, ',', '.'))
                                    ->helperText('O CDC limita a multa a 2%.'),

                                TextInput::make('juros_mensal_percentual')
                                    ->label('Juros de mora (% ao mês)')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->maxValue(EncargosAtrasoService::JUROS_MENSAL_MAXIMO_CONFIGURAVEL)
                                    ->suffix('%')
                                    ->placeholder(fn (): string => number_format((float) config('financeiro.encargos.juros_mensal_percentual', 1), 2, ',', '.'))
                                    ->helperText('Padrão contratual da escola.'),
                            ]),
                    ]),

                Section::make('Assinatura')
                    ->description('Registro de data de conclusão das assinaturas.')
                    ->schema([
                        DateTimePicker::make('data_aceite')
                            ->label('Data de Aceite')
                            ->displayFormat('d/m/Y H:i')
                            ->helperText('Preenchida automaticamente quando a última assinatura é concluída. Pode ser informada manualmente (por exemplo, contrato assinado em papel). Não é necessária para gerar as faturas: elas costumam ser geradas antes da assinatura e, sem aceite, partem da data em que são geradas.'),
                    ]),

                Section::make('Responsáveis financeiros')
                    ->description('Indique quem responde financeiramente pelas faturas e a respectiva cota (%).')
                    ->schema([
                        Repeater::make('responsaveisFinanceiros')
                            ->relationship('responsaveisFinanceiros')
                            ->itemLabel(function (array $state): ?string {
                                if (! empty($state['pessoa_id'])) {
                                    $p = Pessoa::find($state['pessoa_id']);
                                    $percent = isset($state['percentual']) ? " ({$state['percentual']}%)" : '';

                                    return $p ? "{$p->nome}{$percent}" : null;
                                }

                                return 'Novo responsável';
                            })
                            ->addActionLabel('Adicionar responsável')
                            ->schema([
                                Select::make('pessoa_id')
                                    ->relationship('pessoa', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->label('Responsável Financeiro'),

                                TextInput::make('percentual')
                                    ->label('Participação (%)')
                                    ->required()
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(100),
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Registro técnico')
                    ->description('Dados de auditoria interna e metadados de assinatura.')
                    ->collapsed()
                    ->visible(fn (?Contrato $record): bool => $record !== null && filled($record->log_assinatura))
                    ->schema([
                        Textarea::make('log_assinatura')
                            ->label('Log Técnico de Assinatura')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?Contrato $record): bool => $record !== null && filled($record->log_assinatura))
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
