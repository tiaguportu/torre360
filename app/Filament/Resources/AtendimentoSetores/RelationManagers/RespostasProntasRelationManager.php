<?php

namespace App\Filament\Resources\AtendimentoSetores\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Textos prontos do setor para o atendente escolher ao responder (Onda 83). A pessoa sempre revisa antes de enviar.
 */
class RespostasProntasRelationManager extends RelationManager
{
    protected static string $relationship = 'respostasProntas';

    protected static ?string $title = 'Respostas prontas';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('titulo')->label('Título')->required()->maxLength(255),
            Textarea::make('texto')
                ->label('Texto')
                ->helperText('Variáveis: {{ SOLICITANTE }}, {{ ALUNO }}, {{ PROTOCOLO }} e {{ ESCOLA }}. Evite valores e prazos que mudam: o texto vira promessa da escola.')
                ->required()
                ->rows(6),
            Toggle::make('ativo')->label('Disponível')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')->label('Título')->searchable(),
                TextColumn::make('texto')->label('Texto')->limit(60),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->stackedOnMobile();
    }
}
