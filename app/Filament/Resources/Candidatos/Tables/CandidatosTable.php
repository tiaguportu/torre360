<?php

declare(strict_types=1);

namespace App\Filament\Resources\Candidatos\Tables;

use App\Enums\StatusCandidato;
use App\Models\Candidato;
use App\Models\User;
use App\Services\CurriculoIaService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CandidatosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto_path')
                    ->label('Foto')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=C&background=e2e8f0&color=64748b'),

                TextColumn::make('nome_completo')
                    ->label('Nome Completo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Candidato $record): ?string => $record->email ?: $record->telefone),

                TextColumn::make('cargo_pretendido')
                    ->label('Vaga Pretendida')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('escola_atual')
                    ->label('Escola Atual')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('local_moradia')
                    ->label('Onde Mora')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('pretensao_salarial')
                    ->label('Pretensão Salarial')
                    ->money('BRL')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                IconColumn::make('curriculo_path')
                    ->label('Currículo')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray'),

                IconColumn::make('audio_entrevista_path')
                    ->label('Áudio')
                    ->boolean()
                    ->trueIcon('heroicon-o-speaker-wave')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('purple')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('Cadastrado Em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusCandidato::class),

                TernaryFilter::make('tem_curriculo')
                    ->label('Currículo Anexado')
                    ->placeholder('Todos')
                    ->trueLabel('Com currículo')
                    ->falseLabel('Sem currículo')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('curriculo_path'),
                        false: fn ($query) => $query->whereNull('curriculo_path'),
                    ),

                TernaryFilter::make('tem_audio')
                    ->label('Áudio de Entrevista')
                    ->placeholder('Todos')
                    ->trueLabel('Com áudio')
                    ->falseLabel('Sem áudio')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('audio_entrevista_path'),
                        false: fn ($query) => $query->whereNull('audio_entrevista_path'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('analisarAudioModal')
                    ->label('Áudio RH')
                    ->icon('heroicon-o-microphone')
                    ->color('purple')
                    ->modalHeading('🎙️ Entrevista de RH & Análise de Áudio (WhatsApp)')
                    ->modalDescription('Envie o áudio de WhatsApp gravado pelo entrevistador para consolidar o parecer de RH e atualizar os dados cadastrais.')
                    ->modalSubmitActionLabel('Interpretar com IA ✨')
                    ->form([
                        FileUpload::make('audio_arquivo')
                            ->label('Gravação de Áudio do WhatsApp')
                            ->disk('public')
                            ->directory('candidatos/audios')
                            ->acceptedFileTypes([
                                'audio/ogg',
                                'audio/mpeg',
                                'audio/mp3',
                                'audio/wav',
                                'audio/x-m4a',
                                'audio/aac',
                                'audio/opus',
                                'application/ogg',
                            ])
                            ->maxSize(30720)
                            ->required()
                            ->helperText('Formatos: áudios de WhatsApp (.ogg, .opus), .mp3, .m4a, .wav.'),

                        Select::make('entrevistador_user_id')
                            ->label('Entrevistador Responsável')
                            ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                            ->default(fn () => auth()->id())
                            ->searchable()
                            ->preload(),
                    ])
                    ->action(function (array $data, Candidato $record) {
                        $caminhoRelativo = $data['audio_arquivo'] ?? null;
                        if (! $caminhoRelativo) {
                            return;
                        }

                        $caminhoFisico = Storage::disk('public')->path($caminhoRelativo);
                        $mime = @mime_content_type($caminhoFisico) ?: 'audio/ogg';

                        try {
                            /** @var CurriculoIaService $service */
                            $service = app(CurriculoIaService::class);
                            $dadosAtuais = [
                                'nome_completo' => $record->nome_completo,
                                'cargo_pretendido' => $record->cargo_pretendido,
                            ];

                            $resultado = $service->analisarAudioEntrevista($caminhoFisico, $mime, $dadosAtuais);

                            $atualizacoes = [
                                'audio_entrevista_path' => $caminhoRelativo,
                                'entrevistador_user_id' => $data['entrevistador_user_id'] ?? auth()->id(),
                            ];

                            if (! empty($resultado['impressoes_entrevistador'])) {
                                $atualizacoes['impressoes_entrevistador'] = $resultado['impressoes_entrevistador'];
                            }

                            if (! empty($resultado['audio_transcricao_resumo'])) {
                                $atualizacoes['audio_transcricao_resumo'] = $resultado['audio_transcricao_resumo'];
                            }

                            $cadastrais = $resultado['dados_cadastrais_extraidos'] ?? [];
                            if (is_array($cadastrais)) {
                                if (! empty($cadastrais['nome_completo']) && blank($record->nome_completo)) {
                                    $atualizacoes['nome_completo'] = $cadastrais['nome_completo'];
                                }
                                if (! empty($cadastrais['idade']) && blank($record->idade)) {
                                    $atualizacoes['idade'] = (int) $cadastrais['idade'];
                                }
                                if (! empty($cadastrais['data_nascimento']) && blank($record->data_nascimento)) {
                                    $atualizacoes['data_nascimento'] = $cadastrais['data_nascimento'];
                                }
                                if (! empty($cadastrais['cargo_pretendido']) && blank($record->cargo_pretendido)) {
                                    $atualizacoes['cargo_pretendido'] = $cadastrais['cargo_pretendido'];
                                }
                                if (! empty($cadastrais['escola_atual']) && blank($record->escola_atual)) {
                                    $atualizacoes['escola_atual'] = $cadastrais['escola_atual'];
                                }
                                if (! empty($cadastrais['local_moradia']) && blank($record->local_moradia)) {
                                    $atualizacoes['local_moradia'] = $cadastrais['local_moradia'];
                                }
                                if (isset($cadastrais['pretensao_salarial']) && is_numeric($cadastrais['pretensao_salarial']) && blank($record->pretensao_salarial)) {
                                    $atualizacoes['pretensao_salarial'] = (float) $cadastrais['pretensao_salarial'];
                                }
                                if (! empty($cadastrais['formacao_academica']) && blank($record->formacao_academica)) {
                                    $atualizacoes['formacao_academica'] = $cadastrais['formacao_academica'];
                                }
                                if (! empty($cadastrais['experiencias_profissionais']) && blank($record->experiencias_profissionais)) {
                                    $atualizacoes['experiencias_profissionais'] = $cadastrais['experiencias_profissionais'];
                                }
                                if (! empty($cadastrais['habilidades']) && blank($record->habilidades)) {
                                    $atualizacoes['habilidades'] = $cadastrais['habilidades'];
                                }
                            }

                            if (! empty($resultado['parecer_sugerido'])) {
                                $parecer = strtolower((string) $resultado['parecer_sugerido']);
                                $statusEnum = match ($parecer) {
                                    'aprovado' => StatusCandidato::Aprovado,
                                    'reprovado' => StatusCandidato::Reprovado,
                                    'banco_talentos' => StatusCandidato::BancoTalentos,
                                    'entrevistado' => StatusCandidato::Entrevistado,
                                    'em_triagem' => StatusCandidato::EmTriagem,
                                    default => null,
                                };
                                if ($statusEnum) {
                                    $atualizacoes['status'] = $statusEnum;
                                }
                            }

                            $record->update($atualizacoes);

                            Notification::make()
                                ->title('🎙️ Áudio de WhatsApp processado com sucesso!')
                                ->body('As impressões do entrevistador foram consolidadas e os campos foram atualizados no cadastro do candidato.')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Falha ao processar áudio com IA')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('abrirCurriculo')
                    ->label('Currículo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (Candidato $record): ?string => $record->curriculo_url, shouldOpenInNewTab: true)
                    ->visible(fn (Candidato $record): bool => ! empty($record->curriculo_path)),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->stackedOnMobile();
    }
}
