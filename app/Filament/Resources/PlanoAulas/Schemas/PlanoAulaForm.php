<?php

namespace App\Filament\Resources\PlanoAulas\Schemas;

use App\Models\Turma;
use App\Support\TiposArquivo;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PlanoAulaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plano de Aula')
                    ->columns(2)
                    ->schema([
                        Select::make('turma_id')
                            ->label('Turma')
                            ->relationship('turma', 'nome', fn ($query, $record) => $query->vigentes($record?->turma_id))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('disciplina_id', null)),
                        Select::make('disciplina_id')
                            ->label('Disciplina')
                            ->options(function (Get $get) {
                                $turma = Turma::find($get('turma_id'));

                                return $turma?->disciplinas->pluck('nome', 'id') ?? [];
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (Get $get) => ! $get('turma_id')),
                        Select::make('professor_id')
                            ->label('Professor')
                            ->relationship('professor', 'nome')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->user()?->pessoa?->id),
                        DatePicker::make('data_prevista')
                            ->label('Data Prevista')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        Select::make('habilidades')
                            ->label('Habilidades da BNCC Previstas')
                            ->relationship('habilidades', 'nome')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => ($record->codigo ? "[{$record->codigo}] " : '').$record->nome)
                            ->columnSpanFull(),
                        Textarea::make('objetivos')
                            ->label('Objetivos da Aula')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('metodologia')
                            ->label('Metodologia')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('recursos')
                            ->label('Recursos Necessários')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('avaliacao')
                            ->label('Avaliação Prevista')
                            ->rows(2)
                            ->columnSpanFull(),
                        FileUpload::make('anexo_material')
                            ->label('Anexos e Materiais')
                            ->multiple()
                            ->directory('planos-aula')
                            ->acceptedFileTypes(TiposArquivo::materiaisDeAula())
                            ->preserveFilenames()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
