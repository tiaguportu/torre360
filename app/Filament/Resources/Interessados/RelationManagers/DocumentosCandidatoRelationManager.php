<?php

declare(strict_types=1);

namespace App\Filament\Resources\Interessados\RelationManagers;

use App\Enums\SituacaoDocumento;
use App\Models\DocumentoInserido;
use App\Models\TipoDocumento;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentosCandidatoRelationManager extends RelationManager
{
    protected static string $relationship = 'documentosInseridos';

    protected static ?string $title = 'Documentos de Pré-Admissão';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tipo_documento_id')
                    ->label('Tipo de Documento')
                    ->options(TipoDocumento::orderBy('nome')->pluck('nome', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('interessado_dependente_id')
                    ->label('Aluno / Dependente')
                    ->options(fn () => $this->getOwnerRecord()->dependentes()->pluck('nome_crianca', 'id'))
                    ->placeholder('Documento Geral da Família'),

                FileUpload::make('arquivo_path')
                    ->label('Arquivo do Documento')
                    ->disk('local')
                    ->directory('documentos_candidatos/'.$this->getOwnerRecord()->id)
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->required(),

                Select::make('status')
                    ->label('Situação')
                    ->options(SituacaoDocumento::class)
                    ->default(SituacaoDocumento::EM_ANALISE)
                    ->required(),

                Textarea::make('observacoes')
                    ->label('Observações / Motivo da Rejeição')
                    ->placeholder('Caso rejeitado, descreva o que a família precisa corrigir...')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        $progresso = $this->getOwnerRecord()->progressoDocumentos();

        return $table
            ->recordTitleAttribute('nome_arquivo_original')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['tipoDocumento', 'dependente']))
            ->headerActions([
                Action::make('linkPortal')
                    ->label('Copiar Link do Portal')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->action(function () {
                        $url = $this->getOwnerRecord()->urlPortalDocumentos();

                        Notification::make()
                            ->title('Link do Portal de Documentos:')
                            ->body($url)
                            ->success()
                            ->send();
                    }),

                Action::make('enviarWhatsapp')
                    ->label('Enviar Portal por WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->url(function () {
                        $lead = $this->getOwnerRecord();
                        $telefone = preg_replace('/\D/', '', (string) ($lead->pessoa?->telefone ?? ''));
                        if (empty($telefone)) {
                            return null;
                        }
                        if (! str_starts_with($telefone, '55')) {
                            $telefone = '55'.$telefone;
                        }

                        $urlPortal = $lead->urlPortalDocumentos();
                        $nome = $lead->pessoa?->nome ?? 'Família';
                        $msg = rawurlencode("Olá, {$nome}! Para agilizarmos a pré-matrícula, por favor acesse nosso Portal de Admissão seguro para o envio dos documentos necessários:\n\n{$urlPortal}\n\nQualquer dúvida, estamos à disposição!");

                        return "https://api.whatsapp.com/send?phone={$telefone}&text={$msg}";
                    }, shouldOpenInNewTab: true)
                    ->visible(fn () => filled($this->getOwnerRecord()->pessoa?->telefone)),

                CreateAction::make()
                    ->label('Anexar Manualmente')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['interessado_id'] = $this->getOwnerRecord()->id;
                        $data['nome_arquivo_original'] = basename($data['arquivo_path'] ?? 'documento.pdf');

                        return $data;
                    }),
            ])
            ->columns([
                TextColumn::make('tipoDocumento.nome')
                    ->label('Tipo de Documento')
                    ->weight('bold')
                    ->description(fn (DocumentoInserido $record) => $record->tipoDocumento?->flag_obrigatorio ? 'Obrigatório' : 'Opcional')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('dependente.nome_crianca')
                    ->label('Para quem')
                    ->placeholder('Geral da Família')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('nome_arquivo_original')
                    ->label('Arquivo')
                    ->limit(25)
                    ->icon('heroicon-o-paper-clip'),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),

                TextColumn::make('observacoes')
                    ->label('Observações')
                    ->limit(30)
                    ->placeholder('—')
                    ->tooltip(fn (DocumentoInserido $record) => $record->observacoes),

                TextColumn::make('created_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(SituacaoDocumento::class),
            ])
            ->actions([
                Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DocumentoInserido $record) => $record->status !== SituacaoDocumento::VERIFICADO)
                    ->requiresConfirmation()
                    ->action(function (DocumentoInserido $record) {
                        $record->transitionTo(SituacaoDocumento::VERIFICADO);
                        $record->update(['observacoes' => null]);

                        Notification::make()
                            ->title('Documento aprovado!')
                            ->success()
                            ->send();
                    }),

                Action::make('rejeitar')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DocumentoInserido $record) => $record->status !== SituacaoDocumento::REJEITADO)
                    ->form([
                        Textarea::make('motivo')
                            ->label('Motivo da Recusa (visível para os pais no portal)')
                            ->required()
                            ->placeholder('Ex: Foto cortada, documento vencido ou ilegível...'),
                    ])
                    ->action(function (DocumentoInserido $record, array $data) {
                        $record->transitionTo(SituacaoDocumento::REJEITADO);
                        $record->update(['observacoes' => $data['motivo']]);

                        Notification::make()
                            ->title('Documento rejeitado. A família poderá reenviar pelo portal.')
                            ->warning()
                            ->send();
                    }),

                Action::make('baixar')
                    ->label('Baixar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (DocumentoInserido $record) => route('documentos.visualizar', ['path' => $record->arquivo_path]), shouldOpenInNewTab: true),

                EditAction::make(),
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
