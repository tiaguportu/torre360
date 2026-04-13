<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrdemServicoResource\Pages;
use App\Filament\Resources\OrdemServicoResource\RelationManagers;
use App\Filament\Resources\OrdemServicoResource\Widgets;
use App\Models\OrdemServico;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class OrdemServicoResource extends Resource
{
    protected static ?string $model = OrdemServico::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Operacional';

    protected static ?string $modelLabel = 'Ordem de Serviço';

    protected static ?string $pluralModelLabel = 'Ordens de Serviço';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->components([
                        Section::make('Detalhes da Ordem de Serviço')
                            ->components([
                                TextInput::make('titulo')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Título'),
                                Textarea::make('descricao')
                                    ->label('Descrição')
                                    ->columnSpanFull(),
                                Select::make('categorias')
                                    ->relationship('categorias', 'nome')
                                    ->multiple()
                                    ->preload()
                                    ->label('Categorias'),
                            ])->columns(2),

                        Section::make('Mídia')
                            ->components([
                                FileUpload::make('fotos')
                                    ->multiple()
                                    ->image()
                                    ->directory('ordem-servicos')
                                    ->label('Fotos'),
                            ]),
                    ])->columnSpan(['lg' => fn (?OrdemServico $record) => $record === null ? 3 : 2]),

                Group::make()
                    ->components([
                        Section::make('Status e Acompanhamento')
                            ->components([
                                Select::make('status')
                                    ->options([
                                        'Aberta' => 'Aberta',
                                        'Em Andamento' => 'Em Andamento',
                                        'Concluída' => 'Concluída',
                                        'Cancelada' => 'Cancelada',
                                    ])
                                    ->required()
                                    ->default('Aberta'),
                                Select::make('prioridade')
                                    ->options([
                                        'Baixa' => 'Baixa',
                                        'Média' => 'Média',
                                        'Alta' => 'Alta',
                                        'Urgente' => 'Urgente',
                                    ])
                                    ->required()
                                    ->default('Média'),
                                TextInput::make('custo_estimado')
                                    ->label('Custo Estimado')
                                    ->numeric()
                                    ->prefix('R$'),
                                TextInput::make('percentual_conclusao')
                                    ->label('% de Conclusão')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(0)
                                    ->suffix('%'),
                                DatePicker::make('prazo_conclusao')
                                    ->label('Prazo de Conclusão'),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'danger' => 'Cancelada',
                        'warning' => 'Aberta',
                        'success' => 'Concluída',
                        'info' => 'Em Andamento',
                    ])
                    ->sortable(),
                TextColumn::make('prioridade')
                    ->badge()
                    ->colors([
                        'success' => 'Baixa',
                        'warning' => 'Média',
                        'danger' => fn ($state) => in_array($state, ['Alta', 'Urgente']),
                    ])
                    ->sortable(),
                TextColumn::make('percentual_conclusao')
                    ->label('Conclusão')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state.'%')
                    ->sortable(),
                TextColumn::make('custo_estimado')
                    ->label('Custo Estimado')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('prazo_conclusao')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Aberta' => 'Aberta',
                        'Em Andamento' => 'Em Andamento',
                        'Concluída' => 'Concluída',
                        'Cancelada' => 'Cancelada',
                    ]),
                SelectFilter::make('prioridade')
                    ->options([
                        'Baixa' => 'Baixa',
                        'Média' => 'Média',
                        'Alta' => 'Alta',
                        'Urgente' => 'Urgente',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\BulkAction::make('export_pdf')
                        ->label('Exportar PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function (Collection $records) {
                            $pdf = Pdf::loadView('pdf.ordens-servico', ['records' => $records]);

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->output();
                            }, 'ordens-servico.pdf');
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AnotacoesRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\OrdemServicoStats::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdemServicos::route('/'),
            'create' => Pages\CreateOrdemServico::route('/create'),
            'edit' => Pages\EditOrdemServico::route('/{record}/edit'),
        ];
    }
}
