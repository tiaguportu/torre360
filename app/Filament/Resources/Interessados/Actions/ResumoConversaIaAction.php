<?php

declare(strict_types=1);

namespace App\Filament\Resources\Interessados\Actions;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\TipoContatoInteressado;
use App\Services\CrmIaVendasService;
use App\Services\LeadScoreService;
use App\Support\PermissaoAcao;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;

class ResumoConversaIaAction
{
    private const DIRETORIO_AUDIOS = 'temp-conversa-audios';

    public static function make(?string $name = 'resumoConversaIa'): Action
    {
        return Action::make($name)
            ->label('Resumo IA de Conversa (WhatsApp)')
            ->icon('heroicon-o-sparkles')
            ->color('warning')
            // Altera temperatura e próximo contato do lead além de acionar o Gemini.
            ->authorize(PermissaoAcao::qualquer('Update:Interessado'))
            ->modalHeading(fn (Interessado $record): string => "🤖 Síntese IA de Conversa: {$record->pessoa?->nome}")
            ->modalDescription('Cole trechos ou o histórico completo da conversa do WhatsApp e/ou anexe os áudios (mensagens de voz). O Gemini irá extrair perfil, dores, dúvidas, acordos e temperatura.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sintetizar com IA ✨')
            ->modalCancelActionLabel('Cancelar')
            ->form([
                Textarea::make('conversa_texto')
                    ->label('Histórico da Conversa no WhatsApp')
                    ->placeholder("Cole aqui as mensagens trocadas...\nExemplo:\n[14:20] Mãe: Olá, gostaria de saber se vocês têm período integral para o 2º ano...\n[14:25] Consultor: Olá! Sim, temos o integral com almoço e projeto bilíngue...")
                    ->rows(8)
                    // O texto só é obrigatório quando a conversa não chega em áudio.
                    ->required(fn (Get $get): bool => blank($get('audios')))
                    ->helperText('Você pode colar com ou sem data/hora. A IA identifica os participantes e o contexto da conversa. Opcional se anexar áudios.'),

                FileUpload::make('audios')
                    ->label('Áudios da Conversa (opcional)')
                    ->multiple()
                    ->reorderable()
                    ->maxFiles(CrmIaVendasService::MAX_AUDIOS_CONVERSA)
                    ->acceptedFileTypes(array_keys(CrmIaVendasService::MIMES_AUDIO_GEMINI))
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory(self::DIRETORIO_AUDIOS)
                    ->live()
                    ->helperText('Anexe os áudios do WhatsApp (.opus, .ogg, .mp3, .m4a, .wav, .aac, .flac) na ordem em que ocorreram: até '.CrmIaVendasService::MAX_AUDIOS_CONVERSA.' arquivos de 10 MB. A IA ouve, transcreve e inclui o conteúdo na análise.'),

                Toggle::make('salvar_no_historico')
                    ->label('Salvar síntese executiva na linha do tempo do lead')
                    ->default(true),

                Toggle::make('atualizar_temperatura')
                    ->label('Atualizar temperatura do lead com a percepção da IA')
                    ->default(true),

                Toggle::make('atualizar_proximo_contato')
                    ->label('Agendar próximo retorno caso uma data tenha sido combinada na conversa')
                    ->default(true),
            ])
            ->action(function (array $data, Interessado $record): void {
                $service = app(CrmIaVendasService::class);

                // O FileUpload devolve os caminhos já gravados no disco; os arquivos são temporários e saem no `finally`.
                $audiosGravados = array_values(array_filter((array) ($data['audios'] ?? [])));
                $audios = array_map(fn (string $caminho): array => ['caminho' => Storage::disk('local')->path($caminho)], $audiosGravados);

                Notification::make()
                    ->title($audios === [] ? 'Processando diálogo com o Gemini...' : 'Ouvindo os áudios e processando o diálogo com o Gemini...')
                    ->info()
                    ->send();

                try {
                    $resultado = $service->resumirConversaWhatsapp($record, (string) ($data['conversa_texto'] ?? ''), $audios);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Não foi possível analisar a conversa')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                } finally {
                    foreach ($audiosGravados as $caminho) {
                        Storage::disk('local')->delete($caminho);
                    }
                }

                $updates = [];

                if (! empty($data['atualizar_temperatura']) && filled($resultado['temperatura_sugerida'])) {
                    $updates['temperatura'] = $resultado['temperatura_sugerida'];
                }

                if (! empty($data['atualizar_proximo_contato']) && filled($resultado['data_retorno_sugerida'])) {
                    $updates['data_proximo_contato'] = Carbon::parse($resultado['data_retorno_sugerida']);
                }

                if ($updates !== []) {
                    $record->update($updates);
                    LeadScoreService::recalcular($record);
                }

                if (! empty($data['salvar_no_historico'])) {
                    $tipoContato = TipoContatoInteressado::firstOrCreate(['nome' => 'WhatsApp']);

                    HistoricoContato::create([
                        'interessado_id' => $record->id,
                        'usuario_id' => auth()->id() ?? $record->usuario_id,
                        'tipo_contato_interessado_id' => $tipoContato->id,
                        'relato' => $resultado['resumo_markdown'],
                        'data_contato' => now(),
                    ]);
                }

                $msg = 'Conversa resumida com sucesso!';
                if (! empty($updates['temperatura'])) {
                    $msg .= " Temperatura atualizada para: {$updates['temperatura']}.";
                }
                if (! empty($updates['data_proximo_contato'])) {
                    $msg .= ' Próximo contato: '.$updates['data_proximo_contato']->format('d/m/Y').'.';
                }

                Notification::make()
                    ->title('Resumo de Conversa IA Concluído')
                    ->body($msg)
                    ->success()
                    ->duration(8000)
                    ->send();
            });
    }
}
