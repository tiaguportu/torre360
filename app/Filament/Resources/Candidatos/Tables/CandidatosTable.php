<?php

declare(strict_types=1);

namespace App\Filament\Resources\Candidatos\Tables;

use App\Enums\StatusCandidato;
use App\Models\Candidato;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CandidatosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto_path')
                    ->label('Foto')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=C&background=e2e8f0&color=64748b'),

                TextColumn::make('nome_completo')
                    ->label('Nome Completo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Candidato $record): ?string => $record->email ?: $record->telefone),

                TextColumn::make('cargo_pretendido')
                    ->label('Vaga Pretendida')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('escola_atual')
                    ->label('Escola Atual')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('local_moradia')
                    ->label('Onde Mora')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('pretensao_salarial')
                    ->label('Pretensão Salarial')
                    ->money('BRL')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                IconColumn::make('curriculo_path')
                    ->label('Currículo')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray'),

                IconColumn::make('audio_entrevista_path')
                    ->label('Áudio')
                    ->boolean()
                    ->trueIcon('heroicon-o-speaker-wave')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('purple')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('Cadastrado Em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusCandidato::class),

                TernaryFilter::make('tem_curriculo')
                    ->label('Currículo Anexado')
                    ->placeholder('Todos')
                    ->trueLabel('Com currículo')
                    ->falseLabel('Sem currículo')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('curriculo_path'),
                        false: fn ($query) => $query->whereNull('curriculo_path'),
                    ),

                TernaryFilter::make('tem_audio')
                    ->label('Áudio de Entrevista')
                    ->placeholder('Todos')
                    ->trueLabel('Com áudio')
                    ->falseLabel('Sem áudio')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('audio_entrevista_path'),
                        false: fn ($query) => $query->whereNull('audio_entrevista_path'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('abrirCurriculo')
                    ->label('Currículo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (Candidato $record): ?string => $record->curriculo_url, shouldOpenInNewTab: true)
                    ->visible(fn (Candidato $record): bool => ! empty($record->curriculo_path)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->stackedOnMobile();
    }
}
