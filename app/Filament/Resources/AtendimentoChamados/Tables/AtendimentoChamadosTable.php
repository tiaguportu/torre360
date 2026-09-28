<?php

namespace App\Filament\Resources\AtendimentoChamados\Tables;

use App\Enums\PrioridadeChamado;
use App\Enums\StatusChamado;
use App\Models\AtendimentoChamado;
use App\Models\AtendimentoMensagem;
use App\Models\AtendimentoSetor;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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

                TextColumn::make('created_at')
                    ->label('Aberto em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('setor_id')
                    ->label('Setor')
                    ->options(AtendimentoSetor::pluck('nome', 'id')),

                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusChamado::class),

                SelectFilter::make('prioridade')
                    ->label('Prioridade')
                    ->options(PrioridadeChamado::class),
            ])
            ->recordActions([
                Action::make('responder')
                    ->label('Responder')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->modalHeading(fn (AtendimentoChamado $record) => "Responder Chamado: {$record->protocolo}")
                    ->modalDescription(fn (AtendimentoChamado $record) => "Solicitante: {$record->solicitante?->nome} — Assunto: {$record->assunto}")
                    ->form([
                        Select::make('novo_status')
                            ->label('Atualizar Situação do Chamado')
                            ->options(StatusChamado::class)
                            ->default(fn (AtendimentoChamado $record) => $record->status === StatusChamado::Aberto ? StatusChamado::EmAndamento : $record->status)
                            ->required(),

                        Textarea::make('mensagem')
                            ->label('Sua Resposta para a Família')
                            ->placeholder('Escreva aqui as orientações e esclarecimentos...')
                            ->required()
                            ->rows(4),

                        FileUpload::make('anexo_path')
                            ->label('Anexo Opcional (PDF, Imagem, Comprovante)')
                            ->disk('public')
                            ->directory('atendimentos/anexos'),
                    ])
                    ->action(function (AtendimentoChamado $record, array $data) {
                        AtendimentoMensagem::create([
                            'chamado_id' => $record->id,
                            'user_id' => auth()->id(),
                            'mensagem' => $data['mensagem'],
                            'anexo_path' => $data['anexo_path'] ?? null,
                        ]);

                        $record->update([
                            'status' => $data['novo_status'],
                            'responsavel_atendimento_id' => $record->responsavel_atendimento_id ?: auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Resposta enviada com sucesso!')
                            ->body('A família receberá a atualização no Portal.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->stackedOnMobile();
    }
}
