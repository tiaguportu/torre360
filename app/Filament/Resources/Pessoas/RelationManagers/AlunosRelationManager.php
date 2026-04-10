<?php

namespace App\Filament\Resources\Pessoas\RelationManagers;

use App\Models\TipoVinculo;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AlunosRelationManager extends RelationManager
{
    protected static string $relationship = 'alunos';

    protected static ?string $title = 'Alunos';

    protected static ?string $modelLabel = 'Aluno';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Select::make('tipo_vinculo_id')
                    ->label('Tipo de Vínculo')
                    ->options(TipoVinculo::all()->pluck('nome', 'id'))
                    ->required(),
                Toggle::make('permissao_retirada')
                    ->label('Permissão para Retirada'),
                Textarea::make('observacao')
                    ->label('Observação')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                TextColumn::make('nome')
                    ->searchable(),
                TextColumn::make('pivot.tipoVinculo.nome')
                    ->label('Vínculo'),
                IconColumn::make('permissao_retirada')
                    ->label('Retirada')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->authorize('attachAluno')
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('tipo_vinculo_id')
                            ->label('Tipo de Vínculo')
                            ->options(TipoVinculo::all()->pluck('nome', 'id'))
                            ->required(),
                        Toggle::make('permissao_retirada')
                            ->label('Permissão para Retirada'),
                        Textarea::make('observacao')
                            ->label('Observação'),
                    ]),
            ])
            ->inverseRelationship('responsaveis')
            ->recordActions([
                EditAction::make(),
                DetachAction::make()
                    ->authorize('detachAluno'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->authorize('detachAluno'),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
