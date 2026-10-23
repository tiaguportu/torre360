<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosAssinatura\Tables;

use App\Enums\StatusDocumentoAssinatura;
use App\Filament\Resources\DocumentosAssinatura\Actions\AcoesDocumentoAssinatura;
use App\Filament\Resources\DocumentosAssinatura\DocumentoAssinaturaResource;
use App\Models\DocumentoAssinatura;
use App\Models\TemplateDocumento;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentosAssinaturaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Documento')
                    ->description(fn (DocumentoAssinatura $record): string => $record->template?->nome ?? $record->tipoDoRegistro())
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),
                TextColumn::make('andamento')
                    ->label('Assinaturas')
                    ->state(fn (DocumentoAssinatura $record): string => "{$record->assinantes_assinaram}/{$record->assinantes_total}")
                    ->description(fn (DocumentoAssinatura $record): ?string => $record->status === StatusDocumentoAssinatura::Enviado && $record->expira_em ? 'Prazo: '.$record->expira_em->format('d/m/Y') : null),
                TextColumn::make('nivel_assinatura')
                    ->label('Nível da assinatura')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('tipo_do_registro')
                    ->label('Referente a')
                    ->state(fn (DocumentoAssinatura $record): string => $record->tipoDoRegistro())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('enviado_em')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('concluido_em')
                    ->label('Assinado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('criadoPor.name')
                    ->label('Criado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusDocumentoAssinatura::class)
                    ->multiple(),
                SelectFilter::make('template_documento_id')
                    ->label('Modelo')
                    ->options(fn (): array => TemplateDocumento::query()->orderBy('nome')->pluck('nome', 'id')->all()),
            ])
            ->recordActions([
                AcoesDocumentoAssinatura::enviar(),
                ViewAction::make()->label('Abrir'),
                ActionGroup::make([
                    AcoesDocumentoAssinatura::atualizar(),
                    AcoesDocumentoAssinatura::links(),
                    AcoesDocumentoAssinatura::baixarOriginal(),
                    AcoesDocumentoAssinatura::baixarAssinado(),
                    AcoesDocumentoAssinatura::cancelar(),
                ]),
            ])
            ->recordUrl(fn (DocumentoAssinatura $record): string => DocumentoAssinaturaResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->stackedOnMobile();
    }
}
