<?php

namespace App\Filament\Resources\Interessados\Tables;

use App\Models\Interessado;
use App\Models\MensagemWhatsappTemplate;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\LeadScoreService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class InteressadosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_proximo_contato', 'asc')
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Interessado')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('pessoa.telefone')
                    ->label('Telefone')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-phone'),
                TextColumn::make('usuario.name')
                    ->label('Consultor')
                    ->searchable()
                    ->icon('heroicon-o-user'),
                TextColumn::make('origem.nome')
                    ->label('Origem')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status.nome')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state, $record) => $record->status?->cor ?? 'gray')
                    ->sortable(),
                TextColumn::make('lead_score')
                    ->label('Score')
                    ->badge()
                    ->color(fn (?int $state): string => LeadScoreService::cor($state))
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state}" : '—')
                    ->sortable()
                    ->tooltip('Indicador automático 0-100, calculado a partir de perfil, engajamento e intenção do lead.'),
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
                    ->toggleable(),
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
                    ->toggleable(),
                TextColumn::make('valor_estimado')
                    ->label('Valor Est.')
                    ->money('BRL')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('data_proximo_contato')
                    ->label('Prox. Contato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->color(fn ($record) => $record->precisaDeContato() ? 'danger' : null)
                    ->icon(fn ($record) => $record->precisaDeContato() ? 'heroicon-o-exclamation-triangle' : null),
                TextColumn::make('historicos_count')
                    ->label('Contatos')
                    ->counts('historicos')
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
            ])
            ->actions([
                Action::make('registrarAtendimento')
                    ->label('Atendimento')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
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
                            ->options([
                                'agendou_visita' => 'Agendou Visita',
                                'retornar' => 'Retornar depois',
                                'sem_interesse' => 'Sem Interesse',
                                'matriculou' => 'Efetuou Matrícula',
                                'outro' => 'Outro',
                            ]),
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

                        $mensagem = strtr($template?->conteudo ?? '', [
                            '[Nome do Responsável]' => $record->pessoa->nome,
                            '[Nome do Aluno]' => $dependente?->nome_crianca ?? 'aluno(a)',
                            '[Horário de Visita Agendada]' => $record->data_proximo_contato ? $record->data_proximo_contato->format('d/m/Y \à\s H:i').'h' : 'a definir',
                        ]);

                        $telefone = preg_replace('/\D/', '', (string) $record->pessoa->telefone);
                        if (strlen($telefone) <= 11) {
                            $telefone = '55'.$telefone;
                        }

                        $url = 'https://wa.me/'.$telefone.'?text='.urlencode($mensagem);

                        $livewire->js("window.open(".json_encode($url).", '_blank')");
                    }),

                Action::make('finalizarMatricula')
                    ->label('Matricular')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => ! $record->status?->is_ganho)
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

                Action::make('marcarPerdido')
                    ->label('Perdido')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Select::make('motivo_perda')
                            ->label('Motivo da Perda')
                            ->options([
                                'Preço' => 'Preço / Questão financeira',
                                'Concorrência' => 'Escolheu outra escola',
                                'Distância' => 'Distância / Localização',
                                'Mudança' => 'Mudou de cidade',
                                'Desistência' => 'Desistiu de matricular',
                                'Sem retorno' => 'Sem retorno aos contatos',
                                'Outro' => 'Outro',
                            ])
                            ->required(),
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

                            LeadScoreService::recalcular($record);
                        }

                        Notification::make()
                            ->title('Lead marcado como perdido.')
                            ->warning()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
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
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
