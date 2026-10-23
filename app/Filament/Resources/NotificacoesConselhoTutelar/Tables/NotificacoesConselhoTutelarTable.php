<?php

namespace App\Filament\Resources\NotificacoesConselhoTutelar\Tables;

use App\Models\NotificacaoConselhoTutelar;
use App\Models\Turma;
use App\Services\FaltasConselhoTutelarService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NotificacoesConselhoTutelarTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_oficio')
                    ->label('Nº Ofício / FICAI')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('matricula.pessoa.nome')
                    ->label('Estudante')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable(),

                TextColumn::make('total_faltas')
                    ->label('Faltas')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('percentual_faltas')
                    ->label('% Faltas')
                    ->alignCenter()
                    ->suffix('%')
                    ->badge()
                    ->color(fn (NotificacaoConselhoTutelar $record): string => match (true) {
                        (float) $record->percentual_faltas >= 25.0 => 'danger',
                        (float) $record->percentual_faltas >= 7.5 => 'warning',
                        default => 'info',
                    })
                    ->description(fn (NotificacaoConselhoTutelar $record): string => (float) $record->percentual_faltas >= 7.5 ? 'Gatilho LDB atingido' : 'Em monitoramento'),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'notificado' => 'danger',
                        'resolvido' => 'success',
                        'em_analise' => 'warning',
                        'arquivado' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'notificado' => 'Ofício Encaminhado',
                        'resolvido' => 'Resolvido',
                        'em_analise' => 'Em Análise Pedagógica',
                        'arquivado' => 'Arquivado',
                        default => $state,
                    }),

                TextColumn::make('protocolo_conselho')
                    ->label('Protocolo CT')
                    ->placeholder('Pendente')
                    ->toggleable(),

                TextColumn::make('tentativas_contato_count')
                    ->label('Tentativas Contato')
                    ->state(fn (NotificacaoConselhoTutelar $record): int => count($record->tentativas_contato ?? []))
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state >= 2 ? 'success' : 'danger')
                    ->description(fn (int $state): string => $state >= 2 ? 'Recursos esgotados' : 'Pendente contato prévio'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        'em_analise' => 'Em Análise',
                        'notificado' => 'Ofício Encaminhado',
                        'resolvido' => 'Resolvido',
                        'arquivado' => 'Arquivado',
                    ]),

                SelectFilter::make('turma_id')
                    ->label('Turma')
                    ->options(Turma::pluck('nome', 'id')),
            ])
            ->actions([
                Action::make('adicionarContato')
                    ->label('Registrar Tentativa')
                    ->icon('heroicon-o-phone')
                    ->color('info')
                    ->form([
                        DatePicker::make('data')
                            ->label('Data do Contato')
                            ->default(now())
                            ->required(),

                        Select::make('meio')
                            ->label('Canal Utilizado')
                            ->options([
                                'telefone' => 'Ligação Telefônica',
                                'whatsapp' => 'Mensagem WhatsApp',
                                'email' => 'E-mail Formal',
                                'reuniao' => 'Reunião Presencial na Escola',
                                'visita' => 'Visita Domiciliar',
                            ])
                            ->default('telefone')
                            ->required(),

                        TextInput::make('responsavel_contatado')
                            ->label('Nome do Responsável Contatado')
                            ->placeholder('Ex: Mãe / Pai / Responsável Legal')
                            ->required(),

                        Textarea::make('resultado')
                            ->label('Resultado / Justificativa apresentada')
                            ->placeholder('Descreva a conversa e as medidas combinadas...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (NotificacaoConselhoTutelar $record, array $data): void {
                        app(FaltasConselhoTutelarService::class)->registrarTentativaContato($record, $data, auth()->user());

                        Notification::make()
                            ->title('Tentativa de contato registrada com sucesso.')
                            ->success()
                            ->send();
                    }),

                Action::make('visualizarOficio')
                    ->label('Gerar FICAI / Ofício')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->modalHeading(fn (NotificacaoConselhoTutelar $record): string => "Ofício de Comunicação ao Conselho Tutelar #{$record->numero_oficio}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->form([
                        Textarea::make('conteudo_oficio')
                            ->label('Texto Oficial da Notificação (LDB art. 12, VIII / ECA art. 56, II)')
                            ->default(fn (NotificacaoConselhoTutelar $record): string => app(FaltasConselhoTutelarService::class)->gerarTextoOficio($record))
                            ->rows(15)
                            ->columnSpanFull(),
                    ]),

                Action::make('registrarEnvio')
                    ->label('Protocolar Envio')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (NotificacaoConselhoTutelar $record): bool => $record->status === 'em_analise')
                    ->form([
                        TextInput::make('protocolo_conselho')
                            ->label('Número do Protocolo no Conselho Tutelar')
                            ->required(),

                        DatePicker::make('data_notificacao')
                            ->label('Data de Entrega / Envio')
                            ->default(now())
                            ->required(),

                        TextInput::make('nome_conselho_tutelar')
                            ->label('Conselho Tutelar Receptor')
                            ->default(fn (NotificacaoConselhoTutelar $record): ?string => $record->nome_conselho_tutelar),
                    ])
                    ->action(function (NotificacaoConselhoTutelar $record, array $data): void {
                        app(FaltasConselhoTutelarService::class)->registrarProtocoloEnvio(
                            $record,
                            $data['protocolo_conselho'],
                            $data['data_notificacao'],
                            $data['nome_conselho_tutelar']
                        );

                        Notification::make()
                            ->title('Protocolo de envio registrado com sucesso.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->stackedOnMobile();
    }
}
