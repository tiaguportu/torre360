<?php

namespace App\Filament\Resources\Questionarios\Tables;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use App\Models\Questionario;
use App\Models\User;
use App\Notifications\QuestionarioDisponivelNotification;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class QuestionariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable(),
                TextColumn::make('inicio_aplicacao')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('fim_aplicacao')
                    ->label('Fim')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                IconColumn::make('is_anonimo')
                    ->label('Anônimo')
                    ->boolean(),
                IconColumn::make('is_ativo')
                    ->label('Ativo')
                    ->boolean(),
                TextColumn::make('respostas_count')
                    ->label('Respostas')
                    ->counts('respostas')
                    ->badge()
                    ->color('success'),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('responder')
                    ->label('Responder')
                    ->icon('heroicon-o-pencil-square')
                    ->color('success')
                    ->url(fn (Questionario $record): string => QuestionarioResource::getUrl('responder', ['record' => $record]))
                    ->visible(fn (Questionario $record): bool => $record->podeSerRespondidoPor(auth()->user())),
                Action::make('avisarRespondedores')
                    ->label('Avisar Respondedores')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->tooltip(fn (Questionario $record) => $record->ultimo_envio_aviso
                        ? 'Último aviso enviado em: '.$record->ultimo_envio_aviso->format('d/m/Y H:i')
                        : 'Nenhum aviso enviado anteriormente')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar Aviso por E-mail')
                    ->modalDescription(fn (Questionario $record) => 'Deseja realmente enviar um e-mail para avisar todos os possíveis respondedores de que este questionário está disponível? '.($record->ultimo_envio_aviso ? 'Último envio realizado em: '.$record->ultimo_envio_aviso->format('d/m/Y H:i') : 'Nenhum envio realizado anteriormente.'))
                    ->form([
                        Section::make('Destinatários da Notificação')
                            ->collapsed()
                            ->collapsible()
                            ->schema([
                                Toggle::make('exibir_emails')
                                    ->label('Carregar lista de e-mails')
                                    ->live()
                                    ->dehydrated(false),
                                Placeholder::make('lista_emails')
                                    ->label('E-mails que serão notificados')
                                    ->visible(fn ($get) => $get('exibir_emails') === true)
                                    ->content(function (Questionario $record) {
                                        $emails = $record->obterEmailsRespondedores();
                                        if (empty($emails)) {
                                            return 'Nenhum e-mail qualificado encontrado.';
                                        }

                                        return implode(', ', $emails);
                                    }),
                            ]),
                    ])
                    ->action(function (Questionario $record) {
                        $emails = $record->obterEmailsRespondedores();

                        if (empty($emails)) {
                            Notification::make()
                                ->title('Nenhum possível respondedor qualificado encontrado para este questionário.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $usuarios = User::whereIn('email', $emails)->get();

                        foreach ($usuarios as $usuario) {
                            $usuario->notify(new QuestionarioDisponivelNotification($record));
                        }

                        $record->update([
                            'ultimo_envio_aviso' => now(),
                        ]);

                        Notification::make()
                            ->title('Avisos de questionário disparados com sucesso!')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('clonar')
                        ->label('Clonar Selecionados')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->clonar())
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Questionários clonados com sucesso!'),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
