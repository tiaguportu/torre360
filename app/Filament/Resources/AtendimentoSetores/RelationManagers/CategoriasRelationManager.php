<?php

namespace App\Filament\Resources\AtendimentoSetores\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Assuntos do setor ("segunda via de boleto", "horário"), que a família escolhe ao abrir o chamado (Onda 83).
 */
class CategoriasRelationManager extends RelationManager
{
    protected static string $relationship = 'categorias';

    protected static ?string $title = 'Categorias de assunto';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')->label('Assunto')->required()->maxLength(255),
            TextInput::make('ordem')->label('Ordem')->numeric()->default(0),
            Toggle::make('ativo')->label('Disponível para a família')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Assunto')->searchable(),
                TextColumn::make('chamados_count')->label('Chamados')->counts('chamados')->default(0)->toggleable(),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->defaultSort('ordem')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->stackedOnMobile();
    }
}
