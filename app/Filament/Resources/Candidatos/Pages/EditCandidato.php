<?php

declare(strict_types=1);

namespace App\Filament\Resources\Candidatos\Pages;

use App\Enums\StatusCandidato;
use App\Filament\Resources\Candidatos\CandidatoResource;
use App\Models\Candidato;
use App\Models\User;
use App\Services\CurriculoIaService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EditCandidato extends EditRecord
{
    protected static string $resource = CandidatoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analisarAudioModal')
                ->label('Entrevista de RH / Áudio WhatsApp 🎙️✨')
                ->icon('heroicon-o-microphone')
                ->color('purple')
                ->modalHeading('🎙️ Entrevista de RH & Análise de Áudio (WhatsApp)')
                ->modalDescription('Faça upload da mensagem de voz do WhatsApp enviada pelo entrevistador. A IA sintetizará as impressões sobre o candidato, gerará a transcrição dos pontos-chave, sugerirá o status do processo e atualizará os dados cadastrais citados no áudio.')
                ->modalSubmitActionLabel('Interpretar Áudio com IA ✨')
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
                        ->helperText('Formatos aceitos: áudios de WhatsApp (.ogg, .opus), .mp3, .m4a, .wav.'),

                    Select::make('entrevistador_user_id')
                        ->label('Entrevistador Responsável')
                        ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                        ->default(fn () => auth()->id())
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data) {
                    $caminhoRelativo = $data['audio_arquivo'] ?? null;
                    if (! $caminhoRelativo) {
                        return;
                    }

                    $caminhoFisico = Storage::disk('public')->path($caminhoRelativo);
                    $mime = @mime_content_type($caminhoFisico) ?: 'audio/ogg';

                    /** @var Candidato $record */
                    $record = $this->getRecord();

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

                        // Se o entrevistador citou dados cadastrais que estavam vazios no candidato:
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

                        $this->fillForm();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Falha ao processar áudio com IA')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            ViewAction::make(),
            DeleteAction::make(),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData(['content' => $this->getHelpContent()]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        if (! $user || ! $user->can('Update:Candidato')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-4 text-sm">
                <h3 class="font-bold text-base text-gray-900 dark:text-gray-100">Edição de Candidato / Banco de Talentos</h3>
                <p class="text-gray-600 dark:text-gray-400">Revise e atualize os dados do candidato, histórico de entrevistas, foto e parecer de RH.</p>

                <div class="space-y-2">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200">Recursos disponíveis:</h4>
                    <ul class="list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-400">
                        <li><strong>Entrevista de RH / Áudio WhatsApp 🎙️✨:</strong> Clique no botão do cabeçalho para abrir o modal, enviar a gravação de áudio do entrevistador e deixar a IA consolidar o parecer e atualizar dados cadastrais citados no áudio.</li>
                        <li><strong>Currículos em Word e LibreOffice:</strong> Você pode anexar currículos em formatos <code>.doc</code>, <code>.docx</code>, <code>.odt</code>, <code>.pdf</code> ou imagens, com extração de dados e foto automática.</li>
                        <li><strong>Status da Candidatura:</strong> Altere a situação do candidato entre Novo, Em Triagem, Entrevistado, Banco de Talentos, Aprovado ou Reprovado.</li>
                    </ul>
                </div>
            </div>
        ';
    }
}
