<?php

namespace App\Filament\Resources\Interessados\Tables;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Enums\StatusComunicacaoEmMassa;
use App\Enums\StatusVisitaInteressado;
use App\Enums\TipoPublicoComunicacao;
use App\Filament\Pages\EnrollmentWizard;
use App\Filament\Resources\Interessados\Actions\CopilotoMensagemIaAction;
use App\Filament\Resources\Interessados\Actions\DossieIaAction;
use App\Filament\Resources\Interessados\Actions\ResumoConversaIaAction;
use App\Jobs\EnviarComunicacaoEmMassaJob;
use App\Models\CampanhaMarketing;
use App\Models\ComunicacaoEmMassa;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\MensagemWhatsappTemplate;
use App\Models\OrigemInteressado;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\ConsultorWhatsappService;
use App\Services\ConviteMatriculaService;
use App\Services\LeadScoreService;
use App\Services\TermometroVagasService;
use App\Services\VisitaInteressadoService;
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
                            ->options(TipoContatoInteressado::pluck('nome', 'id'))
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
                        $record->historicos()->create([
                            'tipo_contato_interessado_id' => $data['tipo_contato_interessado_id'],
                            'relato' => $data['relato'],
                            'data_contato' => now(),
                            'usuario_id' => auth()->id(),
                            'duracao_minutos' => $data['duracao_minutos'] ?? null,
                            'resultado' => $data['resultado'] ?? null,
                        ]);

                        $updateData = [
                            'data_proximo_contato' => $data['data_proximo_contato'],
                        ];

                        // Registra primeiro contato se ainda não tiver
                        if (! $record->data_primeiro_contato) {
                            $updateData['data_primeiro_contato'] = now();
                        }

                        $record->update($updateData);

                        LeadScoreService::recalcular($record);

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
                    DossieIaAction::make(),
                    CopilotoMensagemIaAction::make(),
                    ResumoConversaIaAction::make(),

                    Action::make('portalDocumentos')
                        ->label('Portal de Pré-Admissão')
                        ->icon('heroicon-o-document-check')
                        ->color('success')
                        ->modalHeading('Portal de Documentos do Candidato')
                        ->modalDescription('Envie este link seguro para a família anexar a documentação de matrícula sem necessidade de login.')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fechar')
                        ->form(fn (Interessado $record) => [
                            TextInput::make('url_portal')
                                ->label('Link Seguro do Portal de Admissão')
                                ->default($record->urlPortalDocumentos())
                                ->readOnly(),
                        ]),

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
                        ->action(function (Interessado $record) {
                            $statusMatriculado = StatusInteressado::where('nome', 'Matriculado')->first();

                            $record->update([
                                'status_interessado_id' => $statusMatriculado?->id,
                                'data_conversao' => now(),
                            ]);

                            LeadScoreService::recalcular($record);

                            Notification::make()
                                ->title('Matrícula finalizada!')
                                ->success()
                                ->send();
                        }),

                    Action::make('gerarConvite')
                        ->label('Gerar Link de Pré-matrícula')
                        ->icon('heroicon-o-link')
                        ->color('info')
                        ->visible(fn (Interessado $record) => ! $record->status?->is_ganho && $record->dependentes()->exists())
                        ->modalHeading('Pré-matrícula Online')
                        ->modalDescription('Envie este link ao responsável para que a própria família preencha a pré-matrícula (responsáveis, alunos e endereço). Os dados chegam pré-preenchidos no Assistente de Matrícula. Válido por 7 dias e de uso único. Atenção: gerar de novo invalida o link anterior.')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fechar')
                        ->form(function (Interessado $record) {
                            $link = app(ConviteMatriculaService::class)->gerarConvite($record);

                            return [
                                TextInput::make('link')
                                    ->label('Link do Convite (copie e envie ao responsável)')
                                    ->default($link)
                                    ->readOnly(),
                            ];
                        }),

                    Action::make('marcarPerdido')
                        ->label('Perdido')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Select::make('motivo_perda')
                                ->label('Motivo da Perda')
                                ->options(Interessado::MOTIVOS_PERDA)
                                ->searchable()
                                ->required(),
                            Textarea::make('observacoes_perda')
                                ->label('Observações / Objeções')
                                ->rows(2)
                                ->placeholder('Detalhes adicionais sobre o encerramento...'),
                        ])
                        ->visible(fn ($record) => ! $record->status?->is_final)
                        ->action(function (array $data, Interessado $record) {
                            $statusPerdido = StatusInteressado::where('nome', 'Perdido')->first()
                                ?? StatusInteressado::where('is_final', true)->where('is_ganho', false)->first();

                            if ($statusPerdido) {
                                $record->update([
                                    'status_interessado_id' => $statusPerdido->id,
                                    'motivo_perda' => $data['motivo_perda'],
                                ]);

                                $relato = "Lead marcado como perdido via tabela ({$statusPerdido->nome}). Motivo: {$data['motivo_perda']}.";
                                if (filled($data['observacoes_perda'] ?? null)) {
                                    $relato .= ' Detalhes: '.trim($data['observacoes_perda']);
                                }

                                HistoricoContato::create([
                                    'interessado_id' => $record->id,
                                    'tipo_contato_interessado_id' => TipoContatoInteressado::where('nome', 'like', '%Presencial%')->value('id') ?? 1,
                                    'data_contato' => now(),
                                    'usuario_id' => auth()->id(),
                                    'relato' => $relato,
                                    'resultado' => 'sem_interesse',
                                ]);

                                LeadScoreService::recalcular($record);
                            }

                            Notification::make()
                                ->title('Lead marcado como perdido.')
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
                                ->options(StatusInteressado::orderBy('ordem')->pluck('nome', 'id'))
                                ->searchable()
                                ->preload()
                                ->native(false),
                            Select::make('usuario_id')
                                ->label('Consultor Responsável')
                                ->options(User::orderBy('name')->pluck('name', 'id'))
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
                                ->options(Interessado::MOTIVOS_PERDA)
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $updateData = array_filter([
                                'status_interessado_id' => $data['status_interessado_id'] ?? null,
                                'usuario_id' => $data['usuario_id'] ?? null,
                                'temperatura' => $data['temperatura'] ?? null,
                                'origem_interessado_id' => $data['origem_interessado_id'] ?? null,
                                'campanha_marketing_id' => $data['campanha_marketing_id'] ?? null,
                                'data_proximo_contato' => $data['data_proximo_contato'] ?? null,
                                'faixa_distancia_escola' => $data['faixa_distancia_escola'] ?? null,
                                'meio_transporte' => $data['meio_transporte'] ?? null,
                                'motivo_perda' => $data['motivo_perda'] ?? null,
                            ], fn ($value) => filled($value));

                            if (empty($updateData)) {
                                Notification::make()
                                    ->title('Nenhum campo foi preenchido')
                                    ->body('Nenhuma alteração foi realizada porque todos os campos foram deixados em branco.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $records->each(function (Interessado $record) use ($updateData) {
                                $record->update($updateData);
                                LeadScoreService::recalcular($record);
                            });

                            Notification::make()
                                ->title("{$records->count()} lead(s) atualizado(s) com sucesso!")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()?->can('Update:Interessado')),
                    BulkAction::make('atribuirConsultor')
                        ->label('Atribuir Consultor')
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Select::make('usuario_id')
                                ->label('Consultor')
                                ->options(User::pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn (Interessado $record) => $record->update([
                                'usuario_id' => $data['usuario_id'],
                            ]));

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
