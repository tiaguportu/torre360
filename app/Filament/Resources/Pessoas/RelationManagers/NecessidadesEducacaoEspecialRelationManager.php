<?php

namespace App\Filament\Resources\Pessoas\RelationManagers;

use App\Models\CategoriaNecessidadeEducacaoEspecial;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NecessidadesEducacaoEspecialRelationManager extends RelationManager
{
    protected static string $relationship = 'necessidadesEducacaoEspecial';

    protected static ?string $title = 'Necessidades de Educação Especial';

    protected static ?string $modelLabel = 'Necessidade de Educação Especial';

    protected static ?string $pluralModelLabel = 'Necessidades de Educação Especial';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('categoria_necessidade_educacao_especial_id')
                    ->label('Categoria de Necessidade de Educação Especial')
                    ->options(CategoriaNecessidadeEducacaoEspecial::pluck('nome', 'id'))
                    ->searchable()
                    ->required(),
                Textarea::make('observacao')
                    ->label('Observação')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('observacao')
            ->columns([
                TextColumn::make('categoria.nome')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('observacao')
                    ->label('Observação')
                    ->limit(50),
                TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->stackedOnMobile();
    }
}
