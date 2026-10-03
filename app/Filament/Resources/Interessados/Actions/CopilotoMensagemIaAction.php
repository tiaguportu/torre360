<?php

declare(strict_types=1);

namespace App\Filament\Resources\Interessados\Actions;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\TipoContatoInteressado;
use App\Services\ConsultorWhatsappService;
use App\Services\CrmIaVendasService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

class CopilotoMensagemIaAction
{
    public static function make(?string $name = 'copilotoIa'): Action
    {
        return Action::make($name)
            ->label('Copiloto WhatsApp IA')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('success')
            ->modalHeading(fn (Interessado $record): string => "💬 Copiloto IA: Mensagem para {$record->pessoa?->nome}")
            ->modalDescription('A IA analisa o histórico do lead e redige uma mensagem personalizada e persuasiva pronta para envio via WhatsApp.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Gerar e Abrir no WhatsApp 🚀')
            ->modalCancelActionLabel('Cancelar')
            ->form([
                Select::make('objetivo')
                    ->label('Objetivo da Mensagem')
                    ->options([
                        'primeiro_contato' => '👋 Primeiro Contato (Boas-vindas acolhedoras)',
                        'convite_visita' => '🏫 Convite para Tour Pedagógico Presencial',
                        'quebra_objecao' => '🛡️ Superar Dúvidas / Objeções (Metodologia, Preço, etc.)',
                        'reativacao' => '🔄 Reativar Família Sumida (Follow-up carinhoso)',
                        'fechamento' => '🎓 Fechamento de Matrícula (Garantia de vaga)',
                    ])
                    ->default('primeiro_contato')
                    ->required(),

                Radio::make('tom')
                    ->label('Tom de Voz')
                    ->options([
                        'acolhedor' => '❤️ Acolhedor & Educacional (Recomendado)',
                        'objetivo' => '⚡ Objetivo & Prático',
                        'inspirador' => '✨ Inspirador & Entusiasta',
                    ])
                    ->default('acolhedor')
                    ->inline()
                    ->required(),

                TextInput::make('instrucoes_extras')
                    ->label('Instrução Específica (Opcional)')
                    ->placeholder('Ex: Mencionar desconto na matrícula até sexta, foco no período integral, etc.')
                    ->maxLength(255),

                Toggle::make('registrar_historico')
                    ->label('Registrar esta tentativa de contato no histórico do lead')
                    ->default(true),
            ])
            ->action(function (array $data, Interessado $record, $livewire): void {
                $service = app(CrmIaVendasService::class);
                $whatsappService = app(ConsultorWhatsappService::class);

                $telefone = $whatsappService->normalizarTelefone($record->pessoa?->telefone);

                if (empty($telefone)) {
                    Notification::make()
                        ->title('Telefone não encontrado')
                        ->body('Este interessado não possui um telefone válido cadastrado para contato via WhatsApp.')
                        ->danger()
                        ->send();

                    return;
                }

                $mensagem = $service->gerarMensagemCopiloto(
                    interessado: $record,
                    objetivo: $data['objetivo'],
                    tom: $data['tom'],
                    instrucoesExtras: ! empty($data['instrucoes_extras']) ? $data['instrucoes_extras'] : null
                );

                if (! empty($data['registrar_historico'])) {
                    $tipoWpp = TipoContatoInteressado::firstOrCreate(['nome' => 'WhatsApp']);

                    HistoricoContato::create([
                        'interessado_id' => $record->id,
                        'usuario_id' => auth()->id() ?? $record->usuario_id,
                        'tipo_contato_interessado_id' => $tipoWpp->id,
                        'data_contato' => now(),
                        'relato' => "💬 Mensagem redigida pelo Copiloto IA (Objetivo: {$data['objetivo']}):\n\n{$mensagem}",
                    ]);
                }

                $params = [];
                if (filled($telefone)) {
                    $params['phone'] = $telefone;
                }
                $params['text'] = $mensagem;

                $url = 'https://api.whatsapp.com/send?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);

                $livewire->js('window.open('.json_encode($url).", '_blank')");

                Notification::make()
                    ->title('Mensagem gerada pelo Copiloto IA!')
                    ->body('Abrindo conversa no WhatsApp com a mensagem personalizada pronta.')
                    ->success()
                    ->send();
            });
    }
}
