<?php

namespace App\Filament\Resources\Interessados\Tables;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Enums\StatusComunicacaoEmMassa;
use App\Enums\StatusVisitaInteressado;
use App\Enums\TipoPublicoComunicacao;
use App\Filament\Pages\EnrollmentWizard;
use App\Filament\Resources\Interessados\Actions\BattlecardAction;
use App\Filament\Resources\Interessados\Actions\CopilotoMensagemIaAction;
use App\Filament\Resources\Interessados\Actions\DossieIaAction;
use App\Filament\Resources\Interessados\Actions\ResumoConversaIaAction;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Jobs\EnviarComunicacaoEmMassaJob;
use App\Models\CampanhaMarketing;
use App\Models\ComunicacaoEmMassa;
use App\Models\Concorrente;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\MensagemWhatsappTemplate;
use App\Models\OrigemInteressado;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\ConsultorWhatsappService;
use App\Services\ConviteMatriculaService;
use App\Services\LeadFunilService;
use App\Services\LeadScoreService;
use App\Services\TermometroVagasService;
use App\Services\VisitaInteressadoService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InteressadosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager loading do render: a ação "Enviar ao consultor" monta a mensagem de cada linha
            // e `precisaDeContato()` (destaque da linha) consulta o último histórico.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([...ConsultorWhatsappService::RELACOES, 'ultimoHistorico', 'visitas.pesquisa', 'visitas.usuario']))
            ->defaultSort('data_proximo_contato', 'asc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->recordClasses(fn (Interessado $record): ?string => $record->precisaDeContato()
                ? 'border-s-4 border-s-danger-500 bg-danger-50/40 dark:bg-danger-400/5'
                : null)
            ->emptyStateIcon('heroicon-o-user-plus')
            ->emptyStateHeading('Nenhum interessado encontrado')
            ->emptyStateDescription('Cadastre um novo lead, importe com IA ou ajuste os filtros e a aba selecionada.')
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Interessado')
                    ->description(fn (Interessado $record): ?string => $record->pessoa?->telefone)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('pessoa', fn (Builder $q) => $q
                        ->where('nome', 'like', "%{$search}%")
                        ->orWhere('telefone', 'like', "%{$search}%")))
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('status.nome')
                    ->label('Status / Consultor')
                    ->badge()
                    ->color(fn ($state, $record) => $record->status?->cor ?? 'gray')
                    ->description(fn (Interessado $record): ?string => $record->usuario?->name)
                    ->sortable(),
                TextColumn::make('lead_score')
                    ->label('Qualificação')
                    ->badge()
                    ->color(fn (?int $state): string => LeadScoreService::cor($state))
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "Score {$state}" : 'Score —')
                    ->description(fn (Interessado $record): string => match ($record->temperatura) {
                        'quente' => '🔥 Quente',
                        'morno' => '🟡 Morno',
                        'frio' => '🔵 Frio',
                        default => 'Temperatura não avaliada',
                    })
                    ->sortable()
                    ->tooltip('Score: indicador automático 0-100. Abaixo, a temperatura definida pelo consultor.'),
                TextColumn::make('data_proximo_contato')
                    ->label('Próximo contato')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Interessado $record): ?string => $record->data_proximo_contato?->diffForHumans())
                    ->sortable()
                    ->color(fn ($record) => $record->precisaDeContato() ? 'danger' : null)
                    ->icon(fn ($record) => $record->precisaDeContato() ? 'heroicon-o-exclamation-triangle' : null),
                TextColumn::make('origem.nome')
                    ->label('Origem')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ultima_visita_nps')
                    ->label('Última Visita / NPS')
                    ->state(function (Interessado $record): string {
                        $visita = $record->visitas->sortByDesc('data_hora')->first();
                        if (! $visita) {
                            return '—';
                        }

                        $data = $visita->data_hora ? $visita->data_hora->format('d/m') : '';

                        if ($visita->status === StatusVisitaInteressado::Realizada) {
                            $pesquisa = $visita->pesquisa;
                            if ($pesquisa && $pesquisa->isRespondida()) {
                                return "🏫 {$data} (NPS {$pesquisa->nota_nps})";
                            }

                            return "🏫 {$data} (Pendente)";
                        }

                        if ($visita->status === StatusVisitaInteressado::Agendada) {
                            return "📅 {$data} (Agendada)";
                        }

                        if ($visita->status === StatusVisitaInteressado::Faltou) {
                            return "❌ {$data} (Faltou)";
                        }

                        return "{$data} ({$visita->status->getLabel()})";
                    })
                    ->badge()
                    ->color(function (Interessado $record): string {
                        $visita = $record->visitas->sortByDesc('data_hora')->first();
                        if (! $visita) {
                            return 'gray';
                        }

                        if ($visita->status === StatusVisitaInteressado::Realizada) {
                            $pesquisa = $visita->pesquisa;
                            if ($pesquisa && $pesquisa->isRespondida()) {
                                return $pesquisa->corBadge();
                            }

                            return 'gray';
                        }

                        if ($visita->status === StatusVisitaInteressado::Agendada) {
                            return 'info';
                        }

                        if ($visita->status === StatusVisitaInteressado::Faltou) {
                            return 'danger';
                        }

                        return 'gray';
                    })
                    ->tooltip(function (Interessado $record): ?string {
                        $visita = $record->visitas->sortByDesc('data_hora')->first();
                        if (! $visita) {
                            return null;
                        }

                        $texto = 'Data: '.($visita->data_hora ? $visita->data_hora->format('d/m/Y H:i') : '—');
                        if ($visita->usuario) {
                            $texto .= " | Consultor: {$visita->usuario->name}";
                        }
                        if ($visita->pesquisa?->isRespondida()) {
                            $texto .= " | NPS: {$visita->pesquisa->nota_nps}/10 ({$visita->pesquisa->classificacaoNps()})";
                            if (filled($visita->pesquisa->comentario)) {
                                $texto .= " | \"{$visita->pesquisa->comentario}\"";
                            }
                        }

                        return $texto;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vagas_serie_interesse')
                    ->label('Vagas na Série')
                    ->state(function (Interessado $record): string {
                        $resumo = app(TermometroVagasService::class)->obterStatusParaLead($record);

                        return $resumo['texto_destaque'] ?? '—';
                    })
                    ->badge()
                    ->color(function (Interessado $record): string {
                        $resumo = app(TermometroVagasService::class)->obterStatusParaLead($record);

                        return $resumo['badge_cor'] ?? 'gray';
                    })
                    ->tooltip(function (Interessado $record): ?string {
                        $resumo = app(TermometroVagasService::class)->obterStatusParaLead($record);
                        if (empty($resumo['series'])) {
                            return null;
                        }

                        return collect($resumo['series'])->map(fn ($s) => "{$s['serie_nome']}: {$s['vagas_restantes']} vagas livres de {$s['capacidade_total']} ({$s['taxa_ocupacao']}%)")->join(' | ');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pessoa.telefone')
                    ->label('Telefone')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('usuario.name')
                    ->label('Consultor')
                    ->searchable()
                    ->icon('heroicon-o-user')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('campanha.nome')
                    ->label('Campanha')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('temperatura_display')
                    ->label('Temp. (consultor)')
                    ->state(fn (Interessado $record): string => match ($record->temperatura) {
                        'quente' => '🔥 Quente',
                        'morno' => '🟡 Morno',
                        'frio' => '🔵 Frio',
                        default => 'Não avaliada',
                    })
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('temperatura', $direction))
                    ->tooltip('Percepção manual do consultor sobre o lead.')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dias_funil')
                    ->label('Dias no Funil')
                    ->state(fn (Interessado $record): string => $record->diasNoFunil().'d')
                    ->color(fn (Interessado $record): string => match (true) {
                        $record->diasNoFunil() > 30 => 'danger',
                        $record->diasNoFunil() > 15 => 'warning',
                        default => 'gray',
                    })
                    ->badge()
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('created_at', $direction === 'asc' ? 'desc' : 'asc'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('valor_estimado')
                    ->label('Valor Est.')
                    ->money('BRL')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('historicos_count')
                    ->label('Contatos')
                    ->counts(['historicos' => fn (Builder $query) => $query->interacoes()])
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dias_sem_interacao')
                    ->label('Sem Interação')
                    ->state(fn (Interessado $record): string => $record->diasSemInteracao().'d')
                    ->color(fn (Interessado $record): string => $record->estaEstagnado() ? 'danger' : 'gray')
                    ->icon(fn (Interessado $record) => $record->estaEstagnado() ? 'heroicon-o-exclamation-circle' : null)
                    ->tooltip('Dias desde a última interação registrada com o lead.')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('redes_sociais')
                    ->label('Redes Sociais')
                    ->state(fn (Interessado $record): array => collect($record->redes_sociais ?? [])
                        ->map(fn (array $rede): string => Interessado::REDES_SOCIAIS[$rede['rede'] ?? ''] ?? 'Outra')
                        ->all())
                    ->url(fn (Interessado $record): ?string => $record->redes_sociais[0]['url'] ?? null, shouldOpenInNewTab: true)
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('faixa_distancia_escola')
                    ->label('Distância')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ate_2km' => 'Até 2km',
                        'de_2_a_5km' => '2 a 5km',
                        'de_5_a_10km' => '5 a 10km',
                        'mais_de_10km' => '+10km',
                        default => '—',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('meio_transporte')
                    ->label('Transporte')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'carro_proprio' => 'Carro próprio',
                        'van_escolar' => 'Van escolar',
                        'transporte_publico' => 'Transporte público',
                        'a_pe_ou_bicicleta' => 'A pé/Bicicleta',
                        default => '—',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->relationship('status', 'nome')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('origem')
                    ->relationship('origem', 'nome')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('campanha')
                    ->label('Campanha')
                    ->relationship('campanha', 'nome')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('consultor')
                    ->relationship('usuario', 'name')
                    ->label('Consultor')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('sem_consultor')
                    ->label('Sem consultor responsável')
                    ->queries(
                        true: fn ($query) => $query->whereNull('usuario_id'),
                        false: fn ($query) => $query->whereNotNull('usuario_id'),
                    ),
                TernaryFilter::make('precisa_contato')
                    ->label('Precisa de Contato')
                    ->queries(
                        true: fn ($query) => $query->precisaContato(),
                        false: fn ($query) => $query->where(function ($q) {
                            $q->whereNull('data_proximo_contato')
                                ->orWhere('data_proximo_contato', '>=', now());
                        }),
                    ),
                SelectFilter::make('temperatura')
                    ->label('Temperatura')
                    ->options([
                        'quente' => '🔥 Quente',
                        'morno' => '🟡 Morno',
                        'frio' => '🔵 Frio',
                    ]),
                TernaryFilter::make('estagnado')
                    ->label('Estagnado (7+ dias sem interação)')
                    ->queries(
                        true: fn ($query) => $query->estagnados(),
                        false: fn ($query) => $query->whereNotIn('id', Interessado::estagnados()->pluck('id')),
                    ),
                SelectFilter::make('situacao_visita')
                    ->label('Visitas à Escola')
                    ->options([
                        'realizada' => '🏫 Já realizaram visita',
                        'agendada' => '📅 Possuem visita agendada',
                        'sem_visita' => '⚪ Ainda não visitaram',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $valor = $data['value'] ?? null;
                        if (blank($valor)) {
                            return $query;
                        }

                        return match ($valor) {
                            'realizada' => $query->whereHas('visitas', fn (Builder $q) => $q->where('status', StatusVisitaInteressado::Realizada)),
                            'agendada' => $query->whereHas('visitas', fn (Builder $q) => $q->where('status', StatusVisitaInteressado::Agendada)->where('data_hora', '>=', now())),
                            'sem_visita' => $query->whereDoesntHave('visitas'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('escassez_vagas')
                    ->label('Disponibilidade de Vagas')
                    ->options([
                        'critico' => '🔥 Séries com últimas vagas (crítico)',
                        'alerta' => '🟡 Séries com vagas limitadas',
                        'disponivel' => '🟢 Séries com vagas abertas',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $valor = $data['value'] ?? null;
                        if (blank($valor)) {
                            return $query;
                        }

                        $service = app(TermometroVagasService::class);
                        $series = $service->calcularVagasPorSerie();

                        $seriesIds = match ($valor) {
                            'critico' => $series->whereIn('nivel_escassez', ['critico', 'esgotado'])->pluck('serie_id'),
                            'alerta' => $series->where('nivel_escassez', 'alerta')->pluck('serie_id'),
                            'disponivel' => $series->where('nivel_escassez', 'disponivel')->pluck('serie_id'),
                            default => collect(),
                        };

                        return $query->whereHas('dependentes', fn ($q) => $q->whereIn('serie_id', $seriesIds));
                    }),
            ])
            ->actions([
                Action::make('registrarAtendimento')
                    ->label('Atendimento')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Registrar atendimento')
                    ->modalHeading('Registrar Atendimento')
                    ->form([
                        Select::make('tipo_contato_interessado_id')
                            ->label('Tipo de Contato')
                            ->options(fn () => TipoContatoInteressado::query()
                                ->whereNotIn('nome', [TipoContatoInteressado::FUNIL, TipoContatoInteressado::FORMULARIO_SITE])
                                ->pluck('nome', 'id'))
                            ->required(),
                        DateTimePicker::make('data_contato')
                            ->label('Quando aconteceu')
                            ->helperText('Deixe o horário atual, ou ajuste se o contato foi mais cedo/em outro dia.')
                            ->default(now())
                            ->maxDate(now())
                            ->seconds(false)
                            ->required(),
                        Textarea::make('relato')
                            ->label('Relato')
                            ->required(),
                        TextInput::make('duracao_minutos')
                            ->label('Duração (minutos)')
                            ->numeric()
                            ->minValue(1),
                        Select::make('resultado')
                            ->label('Resultado do Contato')
                            ->options(HistoricoContato::RESULTADOS),
                        DateTimePicker::make('data_proximo_contato')
                            ->label('Data Próximo Contato')
                            ->default(now()->addDays(2)),
                    ])
                    ->action(function (array $data, Interessado $record) {
                        app(LeadFunilService::class)->registrarAtendimento($record, $data, auth()->id());

                        Notification::make()
                            ->title('Atendimento registrado com sucesso!')
                            ->success()
                            ->send();
                    }),

                Action::make('enviarWhatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Enviar WhatsApp')
                    ->visible(fn (Interessado $record) => filled($record->pessoa?->telefone))
                    ->modalHeading('Enviar Mensagem via WhatsApp')
                    ->form([
                        Select::make('mensagem_whatsapp_template_id')
                            ->label('Modelo de Mensagem')
                            ->options(fn () => MensagemWhatsappTemplate::ativos()->pluck('nome', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('interessado_dependente_id')
                            ->label('Aluno')
                            ->options(fn (Interessado $record) => $record->dependentes->pluck('nome_crianca', 'id'))
                            ->visible(fn (Interessado $record) => $record->dependentes->count() > 1)
                            ->required(fn (Interessado $record) => $record->dependentes->count() > 1),
                    ])
                    ->action(function (array $data, Interessado $record, $livewire) {
                        $template = MensagemWhatsappTemplate::find($data['mensagem_whatsapp_template_id']);

                        $dependente = filled($data['interessado_dependente_id'] ?? null)
                            ? $record->dependentes->firstWhere('id', $data['interessado_dependente_id'])
                            : $record->dependentes->first();

                        $linkPesquisa = '';
                        $ultimaVisita = $record->visitas()->where('status', StatusVisitaInteressado::Realizada)->latest('data_hora')->first()
                            ?? $record->visitas()->latest('data_hora')->first();

                        if ($ultimaVisita) {
                            $linkPesquisa = $ultimaVisita->obterOuCriarPesquisa()->url_publica;
                        }

                        $mensagem = strtr($template?->conteudo ?? '', [
                            '[Nome do Responsável]' => $record->pessoa->nome,
                            '[Nome do Aluno]' => $dependente?->nome_crianca ?? 'aluno(a)',
                            '[Horário de Visita Agendada]' => ($record->proximaVisita?->data_hora ?? $record->data_proximo_contato)?->format('d/m/Y \à\s H:i\h') ?? 'a definir',
                            '[Link da Pesquisa da Visita]' => $linkPesquisa,
                            '[Link da Pesquisa]' => $linkPesquisa,
                            '[Link]' => $linkPesquisa,
                        ]);

                        $telefone = app(ConsultorWhatsappService::class)->normalizarTelefone($record->pessoa?->telefone);

                        $params = [];
                        if (filled($telefone)) {
                            $params['phone'] = $telefone;
                        }
                        $params['text'] = $mensagem;

                        $url = 'https://api.whatsapp.com/send?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);

                        $livewire->js('window.open('.json_encode($url).", '_blank')");
                    }),

                Action::make('enviarAoConsultor')
                    ->label('Enviar ao consultor')
                    ->icon('heroicon-o-share')
                    ->color(fn (Interessado $record): string => app(ConsultorWhatsappService::class)->consultorTemTelefone($record) ? 'success' : 'warning')
                    ->iconButton()
                    ->tooltip(fn (Interessado $record): string => app(ConsultorWhatsappService::class)->consultorTemTelefone($record)
                        ? "Enviar este lead para {$record->usuario->name} no WhatsApp"
                        : "{$record->usuario->name} está sem telefone cadastrado — o WhatsApp abrirá para você escolher o contato")
                    ->visible(fn (Interessado $record): bool => $record->usuario !== null)
                    ->url(fn (Interessado $record): string => app(ConsultorWhatsappService::class)->urlParaInteressado($record))
                    ->openUrlInNewTab(),

                ActionGroup::make([
                    BattlecardAction::make(),
                    DossieIaAction::make(),
                    CopilotoMensagemIaAction::make(),
                    ResumoConversaIaAction::make(),

                    Action::make('agendarVisita')
                        ->label('Agendar Visita')
                        ->icon('heroicon-o-calendar-days')
                        ->color('info')
                        ->visible(fn (Interessado $record) => ! $record->status?->is_final)
                        ->modalHeading('Agendar Visita à Escola')
                        ->form([
                            DateTimePicker::make('data_hora')
                                ->label('Data e Hora')
                                ->seconds(false)
                                ->native(false)
                                ->displayFormat('d/m/Y H:i')
                                ->minDate(now())
                                ->required(),
                            Select::make('interessado_dependente_id')
                                ->label('Aluno')
                                ->options(fn (Interessado $record) => $record->dependentes->pluck('nome_crianca', 'id'))
                                ->placeholder('Toda a família')
                                ->visible(fn (Interessado $record) => $record->dependentes->count() > 1),
                            Textarea::make('observacoes')
                                ->label('Observações')
                                ->rows(2),
                        ])
                        ->action(function (array $data, Interessado $record) {
                            VisitaInteressadoService::agendar(
                                $record,
                                $data['data_hora'],
                                $record->usuario_id ?? auth()->id(),
                                $data['interessado_dependente_id'] ?? null,
                                $data['observacoes'] ?? null,
                            );

                            Notification::make()
                                ->title('Visita agendada com sucesso!')
                                ->success()
                                ->send();
                        }),

                    Action::make('matricular')
                        ->label('Matricular')
                        ->icon('heroicon-o-academic-cap')
                        ->color('success')
                        ->visible(fn (Interessado $record) => ! $record->status?->is_ganho && EnrollmentWizard::canAccess())
                        ->url(fn (Interessado $record) => EnrollmentWizard::getUrl(['interessado' => $record->id])),

                    Action::make('finalizarMatricula')
                        ->label('Marcar matriculado')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => ! $record->status?->is_ganho && ! EnrollmentWizard::canAccess())
                        ->authorize('update')
                        ->action(function (Interessado $record) {
                            try {
                                app(LeadFunilService::class)->marcarMatriculado($record);
                            } catch (DomainException $e) {
                                Notification::make()
                                    ->title('Não foi possível marcar como matriculado')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Matrícula finalizada!')
                                ->success()
                                ->send();
                        }),

                    // Link único do candidato para preenchimento de pré-matrícula e upload de documentos.
                    // O link é gerado no mountUsing (uma única vez) e reaproveitado enquanto for válido.
                    Action::make('gerarConvite')
                        ->label('Link de Admissão & Matrícula')
                        ->icon('heroicon-o-link')
                        ->color('success')
                        ->visible(fn (Interessado $record) => ! $record->status?->is_ganho && $record->dependentes()->exists())
                        ->modalHeading('Portal de Admissão & Matrícula Online')
                        ->modalDescription(fn (Interessado $record): string => "Envie este link seguro e exclusivo para {$record->pessoa?->nome} preencher os dados cadastrais da família e anexar a documentação pelo celular.")
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fechar')
                        ->form([
                            TextInput::make('link')
                                ->label('Link Único da Família (copie e envie ao responsável)')
                                ->readOnly(),
                        ])
                        ->mountUsing(function (Schema $schema, Interessado $record): void {
                            $link = app(ConviteMatriculaService::class)->obterOuGerarConvite($record);
                            $schema->fill(['link' => $link]);
                        }),

                    Action::make('regenerarConvite')
                        ->label('Gerar novo link de pré-matrícula')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (Interessado $record) => ! $record->status?->is_ganho && filled($record->token_convite) && $record->dependentes()->exists())
                        ->authorize('update')
                        ->requiresConfirmation()
                        ->modalHeading('Gerar novo link de pré-matrícula?')
                        ->modalDescription('O link anterior deixa de funcionar. Use quando ele expirou ou foi enviado à pessoa errada.')
                        ->action(function (Interessado $record): void {
                            $link = app(ConviteMatriculaService::class)->gerarConvite($record);

                            Notification::make()
                                ->title('Novo link gerado')
                                ->body($link)
                                ->success()
                                ->persistent()
                                ->send();
                        }),

                    Action::make('propostaComercial')
                        ->label('Simular Proposta Comercial')
                        ->icon('heroicon-o-calculator')
                        ->color('success')
                        ->visible(fn (Interessado $record) => ! $record->status?->is_ganho)
                        ->url(fn (Interessado $record) => url("/admin/proposta-comercials/create?interessado_id={$record->id}")),

                    Action::make('marcarPerdido')
                        ->label('Perdido')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Select::make('motivo_perda')
                                ->label('Motivo da Perda')
                                ->options(Interessado::MOTIVOS_PERDA)
                                ->searchable()
                                ->live()
                                ->required(),
                            Select::make('concorrente_id')
                                ->label('Escola Concorrente Escolhida')
                                ->options(fn () => Concorrente::ativos()->pluck('nome', 'id'))
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => in_array($get('motivo_perda'), ['Concorrência', 'Preço', 'Metodologia', 'Distância'], true))
                                ->placeholder('Selecione a escola concorrente (se aplicável)'),
                            Select::make('fator_decisivo_concorrente')
                                ->label('Fator Decisivo da Família')
                                ->options(Concorrente::FATORES_DECISAO)
                                ->visible(fn (Get $get): bool => filled($get('concorrente_id')) || $get('motivo_perda') === 'Concorrência')
                                ->placeholder('Qual diferencial pesou na decisão dos pais?'),
                            Textarea::make('observacoes_perda')
                                ->label('Observações / Objeções')
                                ->rows(2)
                                ->placeholder('Detalhes adicionais sobre o encerramento...'),
                        ])
                        ->visible(fn ($record) => ! $record->status?->is_final)
                        ->authorize('update')
                        ->action(function (array $data, Interessado $record) {
                            $statusPerdido = StatusInteressado::perdido();

                            if (! $statusPerdido) {
                                Notification::make()
                                    ->title('Não há etapa de perda cadastrada')
                                    ->body('Cadastre um status final de perda (ex.: "Perdido") em Status de Interessado.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            // Inteligência competitiva (Battlecards): a escola concorrente escolhida e o fator decisivo
                            // entram como colunas extras e no relato, sem duplicar o fluxo de perda do LeadFunilService.
                            $concorrente = filled($data['concorrente_id'] ?? null) ? Concorrente::find($data['concorrente_id']) : null;
                            $fatorDecisivo = $data['fator_decisivo_concorrente'] ?? null;

                            $relatoExtra = collect([
                                $concorrente && $data['motivo_perda'] !== LeadFunilService::MOTIVO_CONCORRENCIA ? "Escola concorrente: {$concorrente->nome}." : null,
                                filled($fatorDecisivo) ? "Fator decisivo: {$fatorDecisivo}." : null,
                            ])->filter()->implode(' ');

                            try {
                                app(LeadFunilService::class)->marcarComoPerdido(
                                    $record,
                                    $statusPerdido,
                                    $data['motivo_perda'],
                                    $concorrente?->nome,
                                    $data['observacoes_perda'] ?? null,
                                    auth()->id(),
                                    atributosExtras: [
                                        'concorrente_id' => $concorrente?->id,
                                        'fator_decisivo_concorrente' => $fatorDecisivo,
                                        'detalhes_concorrencia' => $data['observacoes_perda'] ?? null,
                                    ],
                                    relatoExtra: $relatoExtra !== '' ? $relatoExtra : null,
                                );
                            } catch (DomainException $e) {
                                Notification::make()
                                    ->title('Não foi possível marcar como perdido')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Lead marcado como perdido e registrado no radar de inteligência.')
                                ->warning()
                                ->send();
                        }),
                ])
                    ->label('Mais ações')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->tooltip('Mais ações'),
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Editar'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('enviarComunicacaoEmail')
                        ->label('Enviar Comunicação por E-mail')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->visible(fn () => auth()->user()?->can('Create:ComunicacaoEmMassa') && auth()->user()?->can('Enviar:ComunicacaoEmMassa'))
                        ->form([
                            TextInput::make('assunto')
                                ->label('Assunto')
                                ->required()
                                ->maxLength(255),
                            TinyEditor::make('corpo')
                                ->label('Mensagem')
                                ->helperText('Use [Nome] para inserir o primeiro nome do destinatário. Só recebem quem tem e-mail cadastrado e não pediu para não receber comunicações.')
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            $comunicacao = ComunicacaoEmMassa::create([
                                'nome' => 'Envio em lote — '.now()->format('d/m/Y H:i'),
                                'tipo_publico' => TipoPublicoComunicacao::Interessados,
                                'filtros' => ['interessado_ids' => $records->pluck('id')->all()],
                                'canal' => 'email',
                                'assunto' => $data['assunto'],
                                'corpo' => $data['corpo'],
                                'status' => StatusComunicacaoEmMassa::Rascunho,
                                'enviado_por_user_id' => auth()->id(),
                            ]);

                            EnviarComunicacaoEmMassaJob::dispatch($comunicacao);

                            Notification::make()
                                ->title('Envio iniciado')
                                ->body('A comunicação para os '.$records->count().' lead(s) selecionado(s) foi colocada na fila de envio.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('editarLote')
                        ->label('Editar em Lote')
                        ->icon('heroicon-o-pencil-square')
                        ->modalHeading('Editar Interessados em Lote')
                        ->modalDescription('Preencha apenas os campos que deseja alterar nos leads selecionados. Campos deixados em branco permanecerão inalterados.')
                        ->modalWidth(Width::Large)
                        ->form([
                            Select::make('status_interessado_id')
                                ->label('Status / Etapa do Funil')
                                ->helperText('Etapas de perda exigem o motivo abaixo. "Matriculado" não está na lista: a matrícula é concluída lead a lead, pelo assistente.')
                                ->options(fn () => StatusInteressado::query()->where('is_ganho', false)->orderBy('ordem')->pluck('nome', 'id'))
                                ->searchable()
                                ->preload()
                                ->native(false),
                            Select::make('usuario_id')
                                ->label('Consultor Responsável')
                                ->options(fn () => User::consultoresCrm()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->native(false),
                            Select::make('temperatura')
                                ->label('Temperatura')
                                ->options([
                                    'quente' => '🔥 Quente',
                                    'morno' => '🟡 Morno',
                                    'frio' => '🔵 Frio',
                                ])
                                ->native(false),
                            Select::make('origem_interessado_id')
                                ->label('Origem do Lead')
                                ->options(OrigemInteressado::orderBy('nome')->pluck('nome', 'id'))
                                ->searchable()
                                ->preload()
                                ->native(false),
                            Select::make('campanha_marketing_id')
                                ->label('Campanha de Marketing')
                                ->options(CampanhaMarketing::orderBy('nome')->pluck('nome', 'id'))
                                ->searchable()
                                ->preload()
                                ->native(false),
                            DateTimePicker::make('data_proximo_contato')
                                ->label('Data do Próximo Contato')
                                ->native(false),
                            Select::make('faixa_distancia_escola')
                                ->label('Distância até a Escola')
                                ->options([
                                    'ate_2km' => 'Até 2km',
                                    'de_2_a_5km' => '2 a 5km',
                                    'de_5_a_10km' => '5 a 10km',
                                    'mais_de_10km' => 'Mais de 10km',
                                ])
                                ->native(false),
                            Select::make('meio_transporte')
                                ->label('Meio de Transporte')
                                ->options([
                                    'carro_proprio' => 'Carro próprio',
                                    'van_escolar' => 'Van escolar',
                                    'transporte_publico' => 'Transporte público',
                                    'a_pe_ou_bicicleta' => 'A pé / Bicicleta',
                                ]),
                            Select::make('motivo_perda')
                                ->label('Motivo da Perda')
                                ->helperText('Obrigatório ao mover para uma etapa de perda. Sem mudar a etapa, só corrige o motivo de leads que já estão perdidos.')
                                ->options(Interessado::MOTIVOS_PERDA)
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $camposSimples = array_filter([
                                'usuario_id' => $data['usuario_id'] ?? null,
                                'temperatura' => $data['temperatura'] ?? null,
                                'origem_interessado_id' => $data['origem_interessado_id'] ?? null,
                                'campanha_marketing_id' => $data['campanha_marketing_id'] ?? null,
                                'data_proximo_contato' => $data['data_proximo_contato'] ?? null,
                                'faixa_distancia_escola' => $data['faixa_distancia_escola'] ?? null,
                                'meio_transporte' => $data['meio_transporte'] ?? null,
                            ], fn ($value) => filled($value));

                            $statusAlvo = filled($data['status_interessado_id'] ?? null)
                                ? StatusInteressado::find($data['status_interessado_id'])
                                : null;
                            $motivo = $data['motivo_perda'] ?? null;

                            if ($camposSimples === [] && ! $statusAlvo && blank($motivo)) {
                                Notification::make()
                                    ->title('Nenhum campo foi preenchido')
                                    ->body('Nenhuma alteração foi realizada porque todos os campos foram deixados em branco.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            // As travas do funil valem em lote: perder exige motivo e matricular exige a matrícula.
                            if ($statusAlvo?->is_ganho) {
                                Notification::make()
                                    ->title('Matrícula não pode ser feita em lote')
                                    ->body('Para marcar leads como matriculados conclua a matrícula de cada um pelo Assistente de Matrícula.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            if ($statusAlvo?->isPerda() && blank($motivo)) {
                                Notification::make()
                                    ->title('Informe o motivo da perda')
                                    ->body('Para mover leads a uma etapa de perda o motivo é obrigatório.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $funil = app(LeadFunilService::class);
                            $atualizados = 0;
                            $recusados = 0;

                            foreach ($records as $record) {
                                try {
                                    $atualizacoes = $camposSimples;

                                    if ($statusAlvo?->isPerda()) {
                                        $funil->marcarComoPerdido($record, $statusAlvo, $motivo, null, null, auth()->id());
                                    } elseif ($statusAlvo) {
                                        $funil->moverParaEtapaAtiva($record, $statusAlvo, auth()->id());
                                    } elseif (filled($motivo) && $record->status?->isPerda()) {
                                        $atualizacoes['motivo_perda'] = $motivo;
                                    }

                                    if ($atualizacoes !== []) {
                                        $record->update($atualizacoes);
                                    }

                                    LeadScoreService::recalcular($record);
                                    $atualizados++;
                                } catch (DomainException) {
                                    $recusados++;
                                }
                            }

                            $notificacao = Notification::make()
                                ->title("{$atualizados} lead(s) atualizado(s) com sucesso!");

                            if ($recusados > 0) {
                                $notificacao
                                    ->body("{$recusados} lead(s) não foram alterados porque já estão matriculados.")
                                    ->warning();
                            } else {
                                $notificacao->success();
                            }

                            $notificacao->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()?->can('Update:Interessado')),
                    BulkAction::make('atribuirConsultor')
                        ->label('Atribuir Consultor')
                        ->icon('heroicon-o-user-plus')
                        ->visible(fn () => auth()->user()?->can('Update:Interessado'))
                        ->form([
                            Select::make('usuario_id')
                                ->label('Consultor')
                                ->options(fn () => User::consultoresCrm()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $consultor = User::consultoresCrm()->find($data['usuario_id']);

                            if (! $consultor) {
                                Notification::make()
                                    ->title('Consultor inválido')
                                    ->body('Selecione um usuário com permissão para atender leads.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $records->each(fn (Interessado $record) => $record->update([
                                'usuario_id' => $consultor->id,
                            ]));

                            // Quem recebe leads precisa saber: antes a atribuição era silenciosa.
                            if ($consultor->id !== auth()->id()) {
                                Notification::make()
                                    ->title($records->count() === 1 ? 'Um lead foi atribuído a você' : $records->count().' leads foram atribuídos a você')
                                    ->body('Atribuição feita por '.(auth()->user()?->name ?? 'a equipe').'.')
                                    ->icon('heroicon-o-user-plus')
                                    ->actions([
                                        Action::make('ver')
                                            ->label('Ver meus leads')
                                            ->url(InteressadoResource::getUrl('index', ['tableFilters' => ['consultor' => ['value' => $consultor->id]]]))
                                            ->button(),
                                    ])
                                    ->sendToDatabase($consultor);
                            }

                            Notification::make()
                                ->title('Consultor atribuído a '.$records->count().' lead(s)!')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('enviarAosConsultores')
                        ->label('Enviar aos consultores (WhatsApp)')
                        ->icon('heroicon-o-share')
                        ->color('success')
                        ->modalHeading('Enviar leads aos consultores')
                        ->modalWidth(Width::Large)
                        ->modalContent(fn (Collection $records) => view(
                            'filament.interessados.mensagem-consultores',
                            app(ConsultorWhatsappService::class)->agruparPorConsultor($records),
                        ))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fechar'),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
