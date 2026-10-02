<?php

namespace App\Filament\Resources\Funcionarios\RelationManagers;

use App\Models\ContratoTrabalho;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContratosTrabalhoRelationManager extends RelationManager
{
    protected static string $relationship = 'contratosTrabalho';

    protected static ?string $title = 'Contratos de Trabalho';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('vigencia_inicio')
                    ->label('Início da Vigência')
                    ->native(false)
                    ->required(),
                DatePicker::make('vigencia_fim')
                    ->label('Fim da Vigência')
                    ->native(false)
                    ->helperText('Deixe em branco para o contrato vigente.'),
                TextInput::make('salario')
                    ->label('Salário (R$)')
                    ->prefix('R$')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('vigencia_inicio')
            ->defaultSort('vigencia_inicio', 'desc')
            ->columns([
                TextColumn::make('vigencia_inicio')
                    ->label('Início')
                    ->date('d/m/Y'),
                TextColumn::make('vigencia_fim')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->placeholder('Vigente')
                    ->color(fn ($state) => $state === null ? 'success' : null),
                TextColumn::make('salario')
                    ->label('Salário')
                    ->money('BRL'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Novo Contrato'),
            ])
            ->actions([
                Action::make('aditivo')
                    ->label('Registrar Aditivo')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('warning')
                    ->visible(fn (ContratoTrabalho $record): bool => $record->vigencia_fim === null)
                    ->schema([
                        TextInput::make('novo_salario')
                            ->label('Novo Salário (R$)')
                            ->prefix('R$')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        DatePicker::make('data_aditivo')
                            ->label('Data do Aditivo')
                            ->native(false)
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data, ContratoTrabalho $record): void {
                        $record->registrarAditivo($data['novo_salario'], $data['data_aditivo']);
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
