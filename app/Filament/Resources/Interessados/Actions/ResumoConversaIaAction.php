<?php

declare(strict_types=1);

namespace App\Filament\Resources\Interessados\Actions;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\TipoContatoInteressado;
use App\Services\CrmIaVendasService;
use App\Services\LeadScoreService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

class ResumoConversaIaAction
{
    public static function make(?string $name = 'resumoConversaIa'): Action
    {
        return Action::make($name)
            ->label('Resumo IA de Conversa (WhatsApp)')
            ->icon('heroicon-o-sparkles')
            ->color('warning')
            ->modalHeading(fn (Interessado $record): string => "🤖 Síntese IA de Conversa: {$record->pessoa?->nome}")
            ->modalDescription('Cole trechos ou o histórico completo da conversa do WhatsApp. O Gemini irá extrair perfil, dores, dúvidas, acordos e temperatura.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Sintetizar com IA ✨')
            ->modalCancelActionLabel('Cancelar')
            ->form([
                Textarea::make('conversa_texto')
                    ->label('Histórico da Conversa no WhatsApp')
                    ->placeholder("Cole aqui as mensagens trocadas...\nExemplo:\n[14:20] Mãe: Olá, gostaria de saber se vocês têm período integral para o 2º ano...\n[14:25] Consultor: Olá! Sim, temos o integral com almoço e projeto bilíngue...")
                    ->rows(8)
                    ->required()
                    ->helperText('Você pode colar com ou sem data/hora. A IA identifica os participantes e o contexto da conversa.'),

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

                Notification::make()
                    ->title('Processando diálogo com o Gemini...')
                    ->info()
                    ->send();

                $resultado = $service->resumirConversaWhatsapp($record, $data['conversa_texto']);

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
