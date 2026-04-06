<?php

namespace App\Filament\Resources\Pessoas\RelationManagers;

use App\Filament\Resources\Cidades\Schemas\CidadeForm;
use App\Models\Endereco;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EnderecoRelationManager extends RelationManager
{
    protected static string $relationship = 'endereco';

    protected static ?string $title = 'Endereço';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cidade_id')
                    ->relationship('cidade', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->searchable()
                    ->preload()
                    ->createOptionForm(fn (Schema $schema) => CidadeForm::configure($schema)->getComponents())
                    ->required(),
                TextInput::make('logradouro')
                    ->required()
                    ->maxLength(255),
                TextInput::make('numero')
                    ->maxLength(10),
                TextInput::make('bairro')
                    ->maxLength(255),
                TextInput::make('cep')
                    ->maxLength(10),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('logradouro')
            ->columns([
                TextColumn::make('logradouro')
                    ->searchable(),
                TextColumn::make('numero'),
                TextColumn::make('bairro'),
                TextColumn::make('cidade.nome')
                    ->label('Cidade'),
                TextColumn::make('cep'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, RelationManager $livewire): Model {
                        $endereco = Endereco::create($data);

                        $pessoa = $livewire->getOwnerRecord();
                        $pessoa->endereco()->associate($endereco);
                        $pessoa->save();

                        return $endereco;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
