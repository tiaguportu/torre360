<?php

declare(strict_types=1);

namespace App\Filament\Resources\AvaliacaoDocentes\Schemas;

use App\Models\AvaliacaoDocente;
use App\Models\CicloAvaliacaoDocente;
use App\Models\CriterioAvaliacaoDocente;
use App\Models\Funcionario;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AvaliacaoDocenteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Observação')
                    ->columns(2)
                    ->schema([
                        Select::make('ciclo_id')
                            ->label('Ciclo de avaliação')
                            ->options(fn (?AvaliacaoDocente $record): array => CicloAvaliacaoDocente::query()
                                ->where(fn ($q) => $q->abertos()->when($record?->ciclo_id, fn ($qq) => $qq->orWhere('id', $record->ciclo_id)))
                                ->orderByDesc('data_inicio')
                                ->pluck('nome', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->disabledOn(['edit', 'view']),
                        Select::make('funcionario_id')
                            ->label('Professor avaliado')
                            ->options(fn (?AvaliacaoDocente $record): array => Funcionario::query()
                                ->with('pessoa')
                                ->where(fn ($q) => $q->whereNull('data_desligamento')->when($record?->funcionario_id, fn ($qq) => $qq->orWhere('id', $record->funcionario_id)))
                                ->get()
                                ->sortBy(fn (Funcionario $f): string => (string) $f->pessoa?->nome)
                                ->mapWithKeys(fn (Funcionario $f): array => [$f->id => ($f->pessoa?->nome ?? 'Sem nome').' — '.$f->cargo])
                                ->all())
                            ->searchable()
                            ->required()
                            ->disabledOn(['edit', 'view']),
                        DatePicker::make('data_observacao')
                            ->label('Data da observação')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(today())
                            ->maxDate(today())
                            ->required()
                            ->disabledOn(['edit', 'view']),
                        Select::make('turma_id')
                            ->label('Turma observada (opcional)')
                            ->relationship('turma', 'nome')
                            ->searchable()
                            ->preload(),
                        Placeholder::make('avaliador_nome')
                            ->label('Avaliador')
                            ->content(fn (?AvaliacaoDocente $record): string => $record?->avaliador?->name ?? '—')
                            ->visibleOn(['edit', 'view']),
                        Placeholder::make('pontuacao')
                            ->label('Pontuação consolidada')
                            ->content(fn (?AvaliacaoDocente $record): string => $record?->pontuacao_percentual !== null
                                ? number_format((float) $record->pontuacao_percentual, 1, ',', '.').'%'
                                : 'Calculada ao finalizar')
                            ->visibleOn(['edit', 'view']),
                    ]),
                Section::make('Critérios avaliados')
                    ->description('Dê uma nota a cada critério do instrumento do ciclo. As notas e observações ficam guardadas cifradas.')
                    ->visibleOn(['edit', 'view'])
                    ->schema([
                        Repeater::make('notas')
                            ->relationship('notas')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->mutateRelationshipDataBeforeFillUsing(function (array $data): array {
                                $criterio = CriterioAvaliacaoDocente::find($data['criterio_id'] ?? null);

                                $data['criterio_nome'] = $criterio?->nome ?? '—';
                                $data['criterio_descricao'] = $criterio?->descricao;

                                return $data;
                            })
                            ->columns(3)
                            ->schema([
                                Hidden::make('criterio_id'),
                                TextInput::make('criterio_nome')
                                    ->label('Critério')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),
                                Select::make('nota')
                                    ->label('Nota')
                                    ->options(function ($livewire): array {
                                        $escala = (int) ($livewire->getRecord()?->ciclo?->instrumento?->escala_maxima ?? 5);

                                        return collect(range(1, max(1, $escala)))->mapWithKeys(fn (int $n): array => [$n => (string) $n])->all();
                                    })
                                    ->helperText(fn ($livewire): string => '1 = muito abaixo do esperado; '.((int) ($livewire->getRecord()?->ciclo?->instrumento?->escala_maxima ?? 5)).' = excelente.'),
                                Textarea::make('criterio_descricao')
                                    ->label('O que observar')
                                    ->rows(2)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpanFull()
                                    ->visible(fn ($state): bool => filled($state)),
                                Textarea::make('observacao')
                                    ->label('Observação do avaliador')
                                    ->rows(2)
                                    ->maxLength(3000)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Parecer')
                    ->visibleOn(['edit', 'view'])
                    ->schema([
                        Textarea::make('parecer')
                            ->label('Parecer qualitativo')
                            ->helperText('Pontos fortes, oportunidades de melhoria e combinados com o professor. Guardado cifrado.')
                            ->rows(5)
                            ->maxLength(10000),
                    ]),
            ]);
    }
}
