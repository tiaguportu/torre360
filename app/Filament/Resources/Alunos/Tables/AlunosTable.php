<?php

namespace App\Filament\Resources\Alunos\Tables;

use App\Filament\Resources\Alunos\AlunoResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AlunosTable
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
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Aluno&color=7F9CF5&background=EBF4FF')
                    ->getStateUsing(function ($record) {
                        return $record->foto ?: null;
                    }),

                TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('data_nascimento')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('cpf')
                    ->searchable(),

                TextColumn::make('matriculas.codigo')
                    ->label('Matrícula')
                    ->badge()
                    ->searchable(),

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
                Action::make('lancar_notas')
                    ->label('Lançar Notas')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->url(fn ($record) => AlunoResource::getUrl('lancar-notas', ['record' => $record])),
                Action::make('boletim')
                    ->label('Boletim')
                    ->icon(Heroicon::DocumentText)
                    ->url(fn ($record) => AlunoResource::getUrl('boletim', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
