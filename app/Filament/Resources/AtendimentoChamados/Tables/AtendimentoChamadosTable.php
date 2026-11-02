<?php

namespace App\Filament\Resources\AtendimentoChamados\Tables;

use App\Enums\PrioridadeChamado;
use App\Enums\ResultadoChamado;
use App\Enums\StatusChamado;
use App\Models\AtendimentoChamado;
use App\Models\AtendimentoRespostaPronta;
use App\Models\AtendimentoSetor;
use App\Models\User;
use App\Services\AtendimentoService;
use App\Support\Requerimentos\CatalogoRequerimentos;
use App\Support\TiposArquivo;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AtendimentoChamadosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('protocolo')
                    ->label('Protocolo')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('solicitante.nome')
                    ->label('Solicitante')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->placeholder('Geral / Não informado')
                    ->searchable(),

                TextColumn::make('setor.nome')
                    ->label('Setor')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('categoria.nome')
                    ->label('Categoria')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tipo_requerimento')
                    ->label('Requerimento')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? CatalogoRequerimentos::tipo($state)?->nome : null)
                    ->placeholder('—')
                    ->badge()
                    ->color('warning')
                    ->toggleable(),

                TextColumn::make('assunto')
                    ->label('Assunto')
                    ->limit(35)
                    ->searchable(),

                TextColumn::make('prioridade')
                    ->label('Prioridade')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),

                TextColumn::make('resultado')
                    ->label('Resultado')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('prazo_primeira_resposta_em')
                    ->label('Responder até')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->color(fn (AtendimentoChamado $record): ?string => $record->prazoEstourado() ? 'danger' : null)
                    ->icon(fn (AtendimentoChamado $record): ?string => $record->prazoEstourado() ? 'heroicon-o-exclamation-triangle' : null)
                    ->tooltip(fn (AtendimentoChamado $record): ?string => $record->primeira_resposta_em ? 'Respondido em '.$record->primeira_resposta_em->format('d/m/Y H:i') : ($record->prazoEstourado() ? 'Prazo vencido' : null))
                    ->sortable(),

                TextColumn::make('responsavelAtendimento.name')
                    ->label('Atendente')
                    ->placeholder('Ninguém assumiu')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Aberto em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('setor_id')
                    ->label('Setor')
                    ->options(fn () => AtendimentoSetor::pluck('nome', 'id')),

                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusChamado::class),

                SelectFilter::make('prioridade')
                    ->label('Prioridade')
                    ->options(PrioridadeChamado::class),

                SelectFilter::make('tipo_requerimento')
                    ->label('Requerimento')
                    ->options(fn (): array => collect(CatalogoRequerimentos::todos())->mapWithKeys(fn ($tipo) => [$tipo->chave => $tipo->nome])->all()),

                TernaryFilter::make('requerimentos')
                    ->label('Só requerimentos')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('tipo_requerimento'),
                        false: fn (Builder $query) => $query->whereNull('tipo_requerimento'),
                        blank: fn (Builder $query) => $query,
                    ),

                Filter::make('prazo_estourado')
                    ->label('Prazo estourado')
                    ->query(fn (Builder $query): Builder => $query->comPrazoEstourado()),

                Filter::make('sem_atendente')
                    ->label('Ninguém assumiu')
                    ->query(fn (Builder $query): Builder => $query->emAberto()->whereNull('responsavel_atendimento_id')),

                Filter::make('meus')
                    ->label('Só os meus')
                    ->query(fn (Builder $query): Builder => $query->where('responsavel_atendimento_id', auth()->id())),
            ])
            ->recordActions([
                Action::make('assumir')
                    ->label('Assumir')
                    ->icon('heroicon-o-hand-raised')
                    ->color('gray')
                    ->visible(fn (AtendimentoChamado $record): bool => $record->responsavel_atendimento_id === null && $record->estaEmAberto())
                    ->action(function (AtendimentoChamado $record): void {
                        $assumiu = app(AtendimentoService::class)->assumir($record, auth()->user());

                        $notificacao = Notification::make()->title($assumiu ? 'Chamado assumido.' : 'Outra pessoa assumiu este chamado antes de você.');
                        $assumiu ? $notificacao->success() : $notificacao->warning();
                        $notificacao->send();
                    }),

                Action::make('reatribuir')
                    ->label('Reatribuir')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('gray')
                    ->visible(fn (AtendimentoChamado $record): bool => $record->estaEmAberto() && (bool) auth()->user()?->can('Update:AtendimentoChamado'))
                    ->form([
                        Select::make('user_id')
                            ->label('Passar para')
                            ->options(fn (): array => User::permission('Update:AtendimentoChamado')->ativos()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (AtendimentoChamado $record, array $data): void {
                        app(AtendimentoService::class)->reatribuir($record, User::findOrFail($data['user_id']), auth()->user());

                        Notification::make()->title('Chamado reatribuído.')->success()->send();
                    }),

                Action::make('responder')
                    ->label('Responder')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->modalHeading(fn (AtendimentoChamado $record) => "Responder Chamado: {$record->protocolo}")
                    ->modalDescription(fn (AtendimentoChamado $record) => "Solicitante: {$record->solicitante?->nome} — Assunto: {$record->assunto}")
                    ->form(fn (AtendimentoChamado $record): array => [
                        Select::make('novo_status')
                            ->label('Atualizar Situação do Chamado')
                            ->options(StatusChamado::class)
                            ->default($record->status === StatusChamado::Aberto ? StatusChamado::EmAndamento : $record->status)
                            ->required(),

                        Select::make('resposta_pronta')
                            ->label('Resposta pronta (opcional)')
                            ->helperText('Escolha um texto pronto, revise e ajuste antes de enviar.')
                            ->options(fn (): array => AtendimentoRespostaPronta::query()->doSetor($record->setor_id)->pluck('titulo', 'id')->all())
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->visible(fn (): bool => AtendimentoRespostaPronta::query()->doSetor($record->setor_id)->exists())
                            ->afterStateUpdated(function (mixed $state, Set $set) use ($record): void {
                                $pronta = $state ? AtendimentoRespostaPronta::query()->doSetor($record->setor_id)->find($state) : null;

                                if ($pronta) {
                                    $set('mensagem', $pronta->textoPara($record));
                                }
                            }),

                        Textarea::make('mensagem')
                            ->label('Sua Resposta para a Família')
                            ->placeholder('Escreva aqui as orientações e esclarecimentos...')
                            ->required()
                            ->rows(4),

                        FileUpload::make('anexo_path')
                            ->label('Anexo Opcional (PDF, Imagem, Comprovante)')
                            ->disk('local')
                            ->acceptedFileTypes(TiposArquivo::documentos())
                            ->maxSize(5120)
                            ->directory('atendimentos/anexos'),
                    ])
                    ->action(function (AtendimentoChamado $record, array $data) {
                        $status = $data['novo_status'] instanceof StatusChamado ? $data['novo_status'] : StatusChamado::from($data['novo_status']);

                        app(AtendimentoService::class)->responderEquipe($record, auth()->user(), $data['mensagem'], $data['anexo_path'] ?? null, $status);

                        Notification::make()
                            ->title('Resposta enviada com sucesso!')
                            ->body('A família receberá um aviso para ler no Portal.')
                            ->success()
                            ->send();
                    }),

                Action::make('concluir_requerimento')
                    ->label('Concluir pedido')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (AtendimentoChamado $record): bool => $record->ehRequerimento() && $record->resultado === null)
                    ->modalHeading(fn (AtendimentoChamado $record) => 'Concluir: '.$record->tipo()?->nome)
                    ->modalDescription(fn (AtendimentoChamado $record): Htmlable => self::conferencia($record))
                    ->form(fn (AtendimentoChamado $record): array => self::formularioDeConclusao($record))
                    ->action(function (AtendimentoChamado $record, array $data): void {
                        $resultado = $data['resultado'] instanceof ResultadoChamado ? $data['resultado'] : ResultadoChamado::from($data['resultado']);

                        try {
                            app(AtendimentoService::class)->concluirRequerimento(
                                $record,
                                auth()->user(),
                                $resultado,
                                $data['justificativa'] ?? null,
                                (bool) ($data['aplicar'] ?? false),
                                self::opcoesMarcadas($data),
                            );
                        } catch (\DomainException $e) {
                            Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Pedido concluído.')->body('A família foi avisada.')->success()->send();
                    }),

                EditAction::make(),
            ])
            ->stackedOnMobile();
    }

    /**
     * Mostra, antes de concluir, o que o pedido da família mudaria ao lado do dado atual: nada é aplicado sem esta conferência.
     */
    private static function conferencia(AtendimentoChamado $chamado): Htmlable
    {
        $tratador = $chamado->tipo()?->tratador();
        $linhas = [];

        if ($tratador) {
            foreach ($tratador->diferencas($chamado) as $diferenca) {
                $linhas[] = '<li><strong>'.e($diferenca['rotulo']).':</strong> '
                    .($diferenca['antes'] !== null ? '<s>'.e($diferenca['antes']).'</s> → ' : '')
                    .e((string) $diferenca['depois']).'</li>';
            }
        } else {
            foreach (($chamado->dados ?? []) as $campo => $valor) {
                if (filled($valor) && $campo !== 'comprovante') {
                    $linhas[] = '<li><strong>'.e($chamado->tipo()?->rotuloDe((string) $campo) ?? (string) $campo).':</strong> '.e((string) $valor).'</li>';
                }
            }
        }

        return new HtmlString('<p>Pedido de '.e($chamado->solicitante?->nome ?? 'responsável').'. Confira antes de concluir.</p><ul>'.implode('', $linhas).'</ul>');
    }

    /**
     * @return array<int, mixed>
     */
    private static function formularioDeConclusao(AtendimentoChamado $chamado): array
    {
        $tratador = $chamado->tipo()?->tratador();
        $podeAplicar = $tratador !== null && auth()->user()?->can('Aplicar:AtendimentoChamado');

        $campos = [
            Select::make('resultado')
                ->label('Resultado')
                ->options(ResultadoChamado::class)
                ->default(ResultadoChamado::Atendido)
                ->live()
                ->required(),
        ];

        if ($podeAplicar) {
            $campos[] = Toggle::make('aplicar')
                ->label('Aplicar a alteração no sistema agora')
                ->helperText('Só depois da conferência acima. Fica registrado quem aplicou.')
                ->default(false)
                ->live()
                ->visible(fn (Get $get): bool => self::valorDe($get('resultado')) === ResultadoChamado::Atendido->value);

            foreach ($tratador->opcoes() as $nome => $rotulo) {
                $campos[] = Toggle::make('opcao_'.$nome)
                    ->label($rotulo)
                    ->default(false)
                    ->visible(fn (Get $get): bool => (bool) $get('aplicar') && self::valorDe($get('resultado')) === ResultadoChamado::Atendido->value);
            }
        }

        $campos[] = Textarea::make('justificativa')
            ->label('Mensagem para a família')
            ->helperText('Obrigatória ao indeferir: a família recebe esse motivo.')
            ->rows(3)
            ->required(fn (Get $get): bool => self::valorDe($get('resultado')) === ResultadoChamado::Indeferido->value);

        return $campos;
    }

    /**
     * O estado de um `Select` com enum chega ora como o enum, ora como texto.
     */
    private static function valorDe(mixed $estado): ?string
    {
        return $estado instanceof \BackedEnum ? (string) $estado->value : ($estado === null ? null : (string) $estado);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, bool>
     */
    private static function opcoesMarcadas(array $data): array
    {
        $opcoes = [];

        foreach ($data as $chave => $valor) {
            if (str_starts_with((string) $chave, 'opcao_')) {
                $opcoes[substr((string) $chave, 6)] = (bool) $valor;
            }
        }

        return $opcoes;
    }
}
