<?php

declare(strict_types=1);

namespace App\Filament\Resources\Interessados\RelationManagers;

use App\Enums\SituacaoDocumento;
use App\Jobs\ValidarDocumentoComIaJob;
use App\Models\DocumentoInserido;
use App\Models\Interessado;
use App\Models\TipoDocumento;
use App\Services\SincronizacaoCadastroDocumentoService;
use App\Support\PermissaoAcao;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
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
                    ->options(function () {
                        $lead = $this->getOwnerRecord();
                        $cursosIds = $lead->cursosPretendidosIds();

                        return TipoDocumento::query()
                            ->visivelPortalFamilia()
                            ->paraCursos($cursosIds)
                            ->orderBy('nome')
                            ->pluck('nome', 'id');
                    })
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
        return $table
            ->recordTitleAttribute('nome_arquivo_original')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['tipoDocumento', 'dependente']))
            ->headerActions([
                // Ações personalizadas não herdam a policy: o link do portal dá acesso aos dados da família.
                Action::make('linkPortal')
                    ->label('Copiar Link do Portal')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->authorize(PermissaoAcao::qualquer('Update:Interessado'))
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
                    ->authorize(PermissaoAcao::qualquer('Update:Interessado'))
                    // Como action (e não ->url()): a URL do portal gera/renova o token, e isso não pode acontecer a cada
                    // renderização da tabela — só quando a equipe de fato clica em enviar.
                    ->action(function ($livewire): void {
                        $lead = $this->getOwnerRecord();
                        $telefone = preg_replace('/\D/', '', (string) ($lead->pessoa?->telefone ?? ''));
                        if (empty($telefone)) {
                            return;
                        }
                        if (! str_starts_with($telefone, '55')) {
                            $telefone = '55'.$telefone;
                        }

                        $urlPortal = $lead->urlPortalDocumentos();
                        $nome = $lead->pessoa?->nome ?? 'Família';
                        $dias = Interessado::DIAS_VALIDADE_TOKEN_DOCUMENTOS;
                        $msg = rawurlencode("Olá, {$nome}! Para agilizarmos a pré-matrícula, por favor acesse nosso Portal de Admissão seguro para o envio dos documentos necessários (o link vale por {$dias} dias):\n\n{$urlPortal}\n\nQualquer dúvida, estamos à disposição!");

                        $livewire->js('window.open('.json_encode("https://api.whatsapp.com/send?phone={$telefone}&text={$msg}").", '_blank')");
                    })
                    ->visible(fn () => filled($this->getOwnerRecord()->pessoa?->telefone)),

                CreateAction::make()
                    ->label('Anexar Manualmente')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['interessado_id'] = $this->getOwnerRecord()->id;
                        $data['nome_arquivo_original'] = basename($data['arquivo_path'] ?? 'documento.pdf');

                        return $data;
                    })
                    ->after(function (DocumentoInserido $record) {
                        ValidarDocumentoComIaJob::dispatch($record->id);
                    }),

                Action::make('ajuda')
                    ->label('Ajuda')
                    ->icon('heroicon-o-question-mark-circle')
                    ->color('gray')
                    ->modalHeading('Ajuda: Documentos de Pré-Admissão')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->form([
                        ViewField::make('help_content')
                            ->view('filament.components.help-content')
                            ->viewData([
                                'content' => $this->getHelpContent(),
                            ]),
                    ]),
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

                TextColumn::make('analise_ia')
                    ->label('Análise IA')
                    ->state(function (DocumentoInserido $record): string {
                        if (! $record->temAnaliseIa()) {
                            return $record->created_at?->gt(now()->subMinutes(3)) ? 'Processando...' : 'Não analisado';
                        }

                        $confianca = (int) data_get($record->dados_ia, 'score_confianca', 0);
                        $legivel = $record->isLegivelIa();
                        $confere = $record->confereTipoIa();

                        if ($legivel && $confere && $confianca >= 70) {
                            return "Válido ({$confianca}%)";
                        }

                        return "Atenção ({$confianca}%)";
                    })
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'Válido') => 'success',
                        str_starts_with($state, 'Atenção') => 'warning',
                        str_starts_with($state, 'Processando') => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match (true) {
                        str_starts_with($state, 'Válido') => 'heroicon-o-check-badge',
                        str_starts_with($state, 'Atenção') => 'heroicon-o-exclamation-triangle',
                        str_starts_with($state, 'Processando') => 'heroicon-o-arrow-path',
                        default => 'heroicon-o-minus-circle',
                    })
                    ->tooltip(fn (DocumentoInserido $record) => $record->resumoIa()),

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
                Action::make('diagnosticoIa')
                    ->label('Diagnóstico IA')
                    ->icon('heroicon-o-sparkles')
                    ->color('purple')
                    ->modalHeading(fn (DocumentoInserido $record) => 'Diagnóstico IA: '.($record->tipoDocumento?->nome ?? 'Documento'))
                    ->modalWidth(Width::Large)
                    ->modalContent(fn (DocumentoInserido $record) => view('filament.crm.modal-diagnostico-ia', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->extraModalActions([
                        Action::make('sincronizarCadastro')
                            ->label('Sincronizar com Cadastro')
                            ->icon('heroicon-o-arrow-path-rounded-square')
                            ->color('success')
                            ->authorize(PermissaoAcao::qualquer('Update:Interessado'))
                            ->requiresConfirmation()
                            ->modalDescription('Deseja preencher os dados cadastrais (CPF, RG, Data de Nascimento) a partir dos dados extraídos pela IA deste documento? Só campos ainda vazios são preenchidos e valores inválidos são descartados.')
                            ->action(function (DocumentoInserido $record) {
                                $resultado = app(SincronizacaoCadastroDocumentoService::class)->sincronizar($record, auth()->id());

                                if ($resultado['sem_dados']) {
                                    Notification::make()->title('Nenhum dado extraído disponível para sincronização.')->warning()->send();

                                    return;
                                }

                                $descartados = $resultado['ignorados'] === []
                                    ? null
                                    : "Não aplicados:\n• ".implode("\n• ", $resultado['ignorados']);

                                if ($resultado['alterados'] !== []) {
                                    $notificacao = Notification::make()
                                        ->title('Cadastro sincronizado com sucesso!')
                                        ->body(trim('Campos atualizados: '.implode(', ', $resultado['alterados'])."\n".$descartados))
                                        ->success();

                                    // O que foi descartado precisa ser lido: a notificação só some quando fechada.
                                    if ($descartados !== null) {
                                        $notificacao->persistent();
                                    }

                                    $notificacao->send();

                                    return;
                                }

                                if ($descartados !== null) {
                                    Notification::make()
                                        ->title('Nenhum dado foi aplicado ao cadastro')
                                        ->body($descartados)
                                        ->warning()
                                        ->persistent()
                                        ->send();

                                    return;
                                }

                                Notification::make()
                                    ->title('Os campos correspondentes já estavam preenchidos no cadastro.')
                                    ->info()
                                    ->send();
                            }),

                        Action::make('reanalisar')
                            ->label('Reanalisar com IA')
                            ->icon('heroicon-o-sparkles')
                            ->color('warning')
                            ->authorize(PermissaoAcao::qualquer('Update:Interessado'))
                            ->action(function (DocumentoInserido $record) {
                                ValidarDocumentoComIaJob::dispatch($record->id);
                                Notification::make()
                                    ->title('Reanálise enviada para a IA em segundo plano!')
                                    ->body('O parecer será atualizado em instantes.')
                                    ->info()
                                    ->send();
                            }),
                    ]),

                Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DocumentoInserido $record) => $record->status !== SituacaoDocumento::VERIFICADO)
                    ->authorize(PermissaoAcao::qualquer('Update:Interessado'))
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
                    ->authorize(PermissaoAcao::qualquer('Update:Interessado'))
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

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<p>Este módulo gerencia o checklist de documentos de pré-admissão e matrícula do candidato.</p>';
        $html .= '<h3>Recursos e Funcionalidades:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>🔗 Portal do Candidato:</strong> Clique em "Copiar Link do Portal" ou "Enviar Portal por WhatsApp" para compartilhar o link seguro exclusivo onde os pais enviam fotos e PDFs.</li>';
        $html .= '<li><strong>📑 Validador Inteligente com OCR & IA:</strong> Todos os documentos enviados passam por análise em segundo plano pelo Gemini 2.5 Flash, que afere nitidez, correspondência do tipo, extrai dados cruciais (CPF, RG, Filiação) e aponta eventuais divergências.</li>';
        $html .= '<li><strong>✨ Diagnóstico IA:</strong> Clique no botão roxo "Diagnóstico IA" em qualquer documento para inspecionar o parecer pericial completo, score de confiança e alertas.</li>';
        $html .= '<li><strong>🔄 Sincronização Cadastral com 1 Clique:</strong> No modal de Diagnóstico da IA, use o botão "Sincronizar com Cadastro" para preencher automaticamente CPF, RG e Data de Nascimento na ficha cadastral com os dados originais extraídos.</li>';
        $html .= '<li><strong>✅ Aprovação / ❌ Rejeição:</strong> Você tem controle total. Se rejeitar, insira a orientação que será exibida para os pais no portal para que reenviem uma foto melhor.</li>';

        if ($user && $user->can('Create:Interessado')) {
            $html .= '<li><strong>Anexar Manualmente:</strong> A secretaria pode incluir arquivos recebidos por e-mail ou presencialmente.</li>';
        }

        $html .= '</ul>';
        $html .= '<p><strong>Privacidade (LGPD):</strong> Para a análise por IA, a imagem do documento e os dados cadastrais usados na conferência (nome, nascimento, CPF esperados) são enviados à API do Google Gemini. Se a chave configurada é do plano gratuito do Google AI Studio, o Google pode usar o conteúdo para melhorar seus produtos; os planos pagos têm termos diferentes. Confirme o plano e o contrato de tratamento antes de processar documentos de menores, e use a conferência manual quando a família não concordar (Art. 7º, V, Art. 14 e Art. 33 da LGPD).</p>';

        return $html;
    }
}
