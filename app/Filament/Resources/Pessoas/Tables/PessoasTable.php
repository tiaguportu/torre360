<?php

namespace App\Filament\Resources\Pessoas\Tables;

use App\Models\Perfil;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PessoasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto')
                    ->circular()
                    ->label('')
                    ->width(40)
                    ->height(40)
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Pessoa&color=7F9CF5&background=EBF4FF')
                    ->getStateUsing(function ($record) {
                        return $record->foto ?: null;
                    }),

                TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('perfis.nome')
                    ->badge()
                    ->label('Perfis')
                    ->searchable(),

                TextColumn::make('data_nascimento')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('cpf')
                    ->searchable(),

                TextColumn::make('nacionalidade.nome')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('naturalidade.nome')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('endereco.logradouro')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('raca_cor')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('adicionarPerfis')
                        ->label('Adicionar Perfis')
                        ->icon('heroicon-o-plus-circle')
                        ->form([
                            Select::make('perfis')
                                ->label('Selecione os perfis')
                                ->multiple()
                                ->options(Perfil::all()->pluck('nome', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                $record->perfis()->syncWithoutDetaching($data['perfis']);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
