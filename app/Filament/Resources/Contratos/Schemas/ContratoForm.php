<?php

namespace App\Filament\Resources\Contratos\Schemas;

use App\Models\Matricula;
use App\Models\TemplateContrato;
use App\Services\EncargosAtrasoService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContratoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('template_contrato_id')
                    ->label('Template do Contrato')
                    ->relationship('templateContrato', 'nome')
                    ->default(fn () => TemplateContrato::where('is_padrao', true)->first()?->id)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                Select::make('matricula_id')
                    ->relationship('matricula', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => ($record->turma?->nome ?? 'Sem Turma').' - '.($record->pessoa?->nome ?? 'Sem nome'))
                    ->getSearchResultsUsing(function (string $search) {
                        return Matricula::query()
                            ->with(['pessoa', 'turma'])
                            ->whereHas('pessoa', fn ($query) => $query->where('nome', 'like', "%{$search}%"))
                            ->orWhereHas('turma', fn ($query) => $query->where('nome', 'like', "%{$search}%"))
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($record) => [$record->id => ($record->turma?->nome ?? 'Sem Turma').' - '.($record->pessoa?->nome ?? 'Sem nome')])
                            ->toArray();
                    })
                    ->getOptionLabelsUsing(function (array $values) {
                        return Matricula::query()
                            ->with(['pessoa', 'turma'])
                            ->whereIn('id', $values)
                            ->get()
                            ->mapWithKeys(fn ($record) => [$record->id => ($record->turma?->nome ?? 'Sem Turma').' - '.($record->pessoa?->nome ?? 'Sem nome')])
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Aluno (Matrícula)'),
                TextInput::make('valor_total')
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
                    ->helperText('Em branco: vale o padrão da escola. O CDC limita a multa de mora a 2% (art. 52, § 1º).'),
                TextInput::make('juros_mensal_percentual')
                    ->label('Juros de mora (% ao mês)')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->maxValue(EncargosAtrasoService::JUROS_MENSAL_MAXIMO_CONFIGURAVEL)
                    ->suffix('%')
                    ->placeholder(fn (): string => number_format((float) config('financeiro.encargos.juros_mensal_percentual', 1), 2, ',', '.'))
                    ->helperText('Em branco: vale o padrão da escola. Use o mesmo percentual que está escrito no contrato.'),
                DatePicker::make('data_aceite'),
                Textarea::make('log_assinatura')
                    ->columnSpanFull(),
                Repeater::make('responsaveisFinanceiros')
                    ->relationship('responsaveisFinanceiros')
                    ->schema([
                        Select::make('pessoa_id')
                            ->relationship('pessoa', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Responsável Financeiro'),
                        TextInput::make('percentual')
                            ->required()
                            ->numeric()
                            ->default(100),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
