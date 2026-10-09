<?php

namespace App\Filament\Resources\RegistroRotinaDiarias\Schemas;

use App\Enums\HumorCrianca;
use App\Enums\QuantidadeRefeicao;
use App\Models\Matricula;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RegistroRotinaDiariaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rotina do Dia')
                    ->columns(2)
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Aluno')
                            ->relationship('matricula', 'id', modifyQueryUsing: fn ($query) => $query->with(['pessoa', 'turma']))
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->pessoa?->nome} — {$record->turma?->nome}")
                            ->searchable(['pessoa.nome'])
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('turma_id', Matricula::find($state)?->turma_id);
                            }),
                        Hidden::make('turma_id'),
                        DatePicker::make('data')
                            ->label('Data')
                            ->native(false)
                            ->default(now())
                            ->required()
                            ->unique(
                                table: 'registro_rotina_diarias',
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, Get $get) => $rule->where('matricula_id', $get('matricula_id')),
                            )
                            ->validationMessages([
                                'unique' => 'Já existe um registro de rotina para este aluno nesta data.',
                            ]),
                        Select::make('humor')
                            ->label('Humor')
                            ->options(HumorCrianca::class),
                        TimePicker::make('hora_inicio_soneca')
                            ->label('Início da Soneca')
                            ->seconds(false),
                        TimePicker::make('hora_fim_soneca')
                            ->label('Fim da Soneca')
                            ->seconds(false),
                        Textarea::make('atividades_dia')
                            ->label('Atividades do Dia')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('higiene_observacoes')
                            ->label('Higiene (observações)')
                            ->rows(2)
                            ->columnSpanFull(),
                        FileUpload::make('foto_path')
                            ->label('Foto (opcional)')
                            ->image()
                            ->directory('rotina-diaria')
                            ->columnSpanFull(),
                    ]),
                Section::make('Refeições')
                    ->schema([
                        Repeater::make('refeicoes')
                            ->label('')
                            ->relationship()
                            ->schema([
                                TextInput::make('nome')
                                    ->label('Refeição')
                                    ->placeholder('Ex.: Café da Manhã, Almoço, Lanche da Tarde')
                                    ->required(),
                                Select::make('quantidade')
                                    ->label('Quanto Comeu')
                                    ->options(QuantidadeRefeicao::class)
                                    ->required(),
                                TextInput::make('observacao')
                                    ->label('Observação (opcional)'),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Adicionar Refeição')
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): ?string => $state['nome'] ?? 'Refeição'),
                    ]),
            ]);
    }
}
