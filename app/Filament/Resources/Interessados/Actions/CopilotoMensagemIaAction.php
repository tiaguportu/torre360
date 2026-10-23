<?php

declare(strict_types=1);

namespace App\Filament\Resources\Interessados\Actions;

use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\MensagemWhatsappTemplate;
use App\Models\TipoContatoInteressado;
use App\Services\ConsultorWhatsappService;
use App\Services\CrmIaVendasService;
use App\Support\PermissaoAcao;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Copiloto WhatsApp IA em duas etapas dentro do mesmo modal: o consultor escolhe objetivo e tom, a IA redige a
 * mensagem num campo editável (e pode ser chamada de novo quantas vezes quiser, dentro do limite por minuto), e só
 * então o WhatsApp é aberto com o texto que estiver no campo. A tentativa só vai para o histórico nesse momento,
 * com o texto final, e não mais antes de alguém ter lido a mensagem.
 *
 * Se a família respondeu por áudio, o consultor anexa as mensagens de voz antes de gerar: a IA as ouve e a mensagem
 * responde ao que foi dito. Os arquivos só existem como upload temporário do Livewire, enquanto o modal está aberto
 * (podem ser reaproveitados a cada "gerar de novo"); nada é gravado no histórico, e ao abrir o WhatsApp são apagados.
 */
class CopilotoMensagemIaAction
{
    public static function make(?string $name = 'copilotoIa'): Action
    {
        return Action::make($name)
            ->label('Copiloto WhatsApp IA')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('success')
            ->authorize(PermissaoAcao::qualquer('Update:Interessado', 'Create:HistoricoContato'))
            ->modalHeading(fn (Interessado $record): string => "💬 Copiloto IA: Mensagem para {$record->pessoa?->nome}")
            ->modalDescription('Escolha o objetivo e o tom, peça à IA para redigir a mensagem a partir do histórico do lead e revise o texto antes de abrir o WhatsApp.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Abrir no WhatsApp 🚀')
            ->modalCancelActionLabel('Cancelar')
            ->form(fn (Interessado $record): array => [
                Select::make('mensagem_whatsapp_template_id')
                    ->label('Modelo Institucional de Referência (Opcional)')
                    ->placeholder('Nenhum (Redigir mensagem 100% livre com IA)')
                    ->options(fn () => MensagemWhatsappTemplate::ativos()->pluck('nome', 'id'))
                    ->searchable()
                    ->helperText('Selecione um modelo oficial da escola para o Copiloto IA adaptar e humanizar conforme o contexto deste lead.'),

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

                FileUpload::make('audios')
                    ->label('Áudios da Família (opcional)')
                    ->multiple()
                    ->reorderable()
                    ->maxFiles(CrmIaVendasService::MAX_AUDIOS_CONVERSA)
                    ->acceptedFileTypes(array_keys(CrmIaVendasService::MIMES_AUDIO_GEMINI))
                    ->maxSize(10240)
                    // Só alimentam a geração: não vão para `$data` (`dehydrated`) nem são copiados para um disco
                    // (`storeFiles`; sem isto o Filament os gravaria na pasta padrão ao enviar o formulário, e ninguém os
                    // apagaria). Ficam como upload temporário do Livewire até `descartarAudios()`.
                    ->dehydrated(false)
                    ->storeFiles(false)
                    ->helperText('Se a família respondeu por áudio, anexe as mensagens de voz do WhatsApp (.opus, .ogg, .mp3, .m4a, .wav, .aac, .flac) na ordem em que chegaram: até '.CrmIaVendasService::MAX_AUDIOS_CONVERSA.' arquivos de 10 MB. A IA ouve e a mensagem responde ao que foi dito. Os áudios não são gravados no histórico do lead.'),

                Actions::make([
                    Action::make('gerarMensagemCopiloto')
                        ->label('✨ Gerar mensagem com a IA')
                        ->color('primary')
                        ->action(fn (Get $get, Set $set) => self::gerarMensagem($record, $get, $set)),
                ])->key('acoesMensagem'),

                Textarea::make('mensagem')
                    ->label('Mensagem para o WhatsApp (revise antes de abrir)')
                    ->placeholder('Clique em "Gerar mensagem com a IA" ou escreva a mensagem aqui.')
                    ->rows(9)
                    ->maxLength(2000)
                    ->required()
                    ->validationMessages(['required' => 'Gere a mensagem com a IA ou escreva uma antes de abrir o WhatsApp.'])
                    ->helperText(fn (Get $get): string => $get('mensagem_contingencia')
                        ? '⚠️ A IA não respondeu agora e este é um texto padrão. Ajuste-o ou clique em "Gerar mensagem com a IA" para tentar de novo.'
                        : 'O que estiver neste campo é exatamente o que será aberto no WhatsApp. Edite à vontade.'),

                Hidden::make('mensagem_original'),
                Hidden::make('mensagem_contingencia')->default(false),
                // Quantos áudios a IA de fato ouviu na última geração, para o histórico dizer em que a mensagem se baseou.
                Hidden::make('audios_considerados')->default(0),

                Toggle::make('registrar_historico')
                    ->label('Registrar esta tentativa de contato no histórico do lead')
                    ->default(true),
            ])
            ->action(function (array $data, Interessado $record, $livewire, Action $action): void {
                // Os áudios já cumpriram o papel (gerar a mensagem): saem do servidor ao abrir o WhatsApp. O campo não
                // é desidratado, então não vem em `$data`; os uploads temporários são lidos do estado bruto da ação.
                self::descartarAudios($action->getRawData()['audios'] ?? []);

                $telefone = app(ConsultorWhatsappService::class)->normalizarTelefone($record->pessoa?->telefone);

                if (empty($telefone)) {
                    self::avisarSemTelefone();

                    return;
                }

                $mensagem = trim((string) ($data['mensagem'] ?? ''));

                if (! empty($data['registrar_historico'])) {
                    $template = filled($data['mensagem_whatsapp_template_id'] ?? null)
                        ? MensagemWhatsappTemplate::find($data['mensagem_whatsapp_template_id'])
                        : null;

                    HistoricoContato::create([
                        'interessado_id' => $record->id,
                        'usuario_id' => auth()->id() ?? $record->usuario_id,
                        'tipo_contato_interessado_id' => TipoContatoInteressado::firstOrCreate(['nome' => 'WhatsApp'])->id,
                        'data_contato' => now(),
                        'relato' => self::relatoDoHistorico(
                            mensagem: $mensagem,
                            original: trim((string) ($data['mensagem_original'] ?? '')),
                            contingencia: ! empty($data['mensagem_contingencia']),
                            objetivo: (string) ($data['objetivo'] ?? ''),
                            nomeModelo: $template?->nome,
                            audios: max(0, min((int) ($data['audios_considerados'] ?? 0), CrmIaVendasService::MAX_AUDIOS_CONVERSA)),
                        ),
                        'automatico' => true,
                    ]);
                }

                $url = 'https://api.whatsapp.com/send?'.http_build_query(['phone' => $telefone, 'text' => $mensagem], '', '&', PHP_QUERY_RFC3986);

                $livewire->js('window.open('.json_encode($url).", '_blank')");

                Notification::make()
                    ->title('Abrindo o WhatsApp')
                    ->body('A conversa foi aberta com a mensagem que você revisou.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Pede a mensagem à IA com o que está escolhido no formulário e a coloca no campo editável. Nada é gravado aqui:
     * só consome uma chamada do limite por minuto e preenche o campo (e avisa se caiu na mensagem de contingência).
     */
    private static function gerarMensagem(Interessado $record, Get $get, Set $set): void
    {
        $telefone = app(ConsultorWhatsappService::class)->normalizarTelefone($record->pessoa?->telefone);

        if (empty($telefone)) {
            self::avisarSemTelefone();

            return;
        }

        $rateLimit = CrmIaVendasService::verificarIncrementarRateLimit();

        if (! $rateLimit['permitido']) {
            Notification::make()
                ->title('Limite de consultas de IA atingido')
                ->body($rateLimit['mensagem'])
                ->warning()
                ->send();

            return;
        }

        $template = filled($get('mensagem_whatsapp_template_id'))
            ? MensagemWhatsappTemplate::ativos()->find($get('mensagem_whatsapp_template_id'))
            : null;

        $audios = self::audiosAnexados($get('audios'));

        try {
            $resultado = app(CrmIaVendasService::class)->redigirMensagemCopiloto(
                interessado: $record,
                objetivo: (string) ($get('objetivo') ?: 'primeiro_contato'),
                tom: (string) ($get('tom') ?: 'acolhedor'),
                instrucoesExtras: filled($get('instrucoes_extras')) ? (string) $get('instrucoes_extras') : null,
                templateBase: $template?->conteudo,
                instrucoesModelo: $template?->instrucoes_ia,
                audios: $audios,
            );
        } catch (InvalidArgumentException $e) {
            // Áudio com formato sem suporte, acima do limite ou sumido: o consultor precisa trocar o anexo, e a
            // mensagem padrão não resolveria (ela ignoraria o que a família disse).
            Notification::make()
                ->title('Não foi possível usar os áudios')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $set('mensagem', $resultado['mensagem']);
        $set('mensagem_original', $resultado['mensagem']);
        $set('mensagem_contingencia', $resultado['contingencia']);
        $set('audios_considerados', $resultado['contingencia'] ? 0 : count($audios));

        if ($resultado['contingencia']) {
            Notification::make()
                ->title('A IA não respondeu agora')
                ->body('Coloquei uma mensagem padrão no campo, sem a personalização da IA'
                    .($audios === [] ? '' : ' e sem considerar os áudios')
                    .'. Ajuste o texto ou clique em "Gerar mensagem com a IA" para tentar de novo.')
                ->warning()
                ->duration(10000)
                ->send();
        }
    }

    /**
     * Áudios anexados ao modal, no formato que o serviço espera. Só valem os uploads temporários do Livewire: no
     * estado do formulário um texto no lugar do arquivo seria um caminho escolhido pelo navegador, e jamais deve
     * ser lido no servidor para virar conteúdo enviado à IA.
     *
     * @return list<array{caminho: string, mime: string}>
     */
    private static function audiosAnexados(mixed $estado): array
    {
        return collect(Arr::wrap($estado))
            ->filter(fn (mixed $arquivo): bool => $arquivo instanceof TemporaryUploadedFile)
            ->map(fn (TemporaryUploadedFile $arquivo): array => ['caminho' => $arquivo->getRealPath(), 'mime' => $arquivo->getMimeType()])
            ->values()
            ->all();
    }

    private static function descartarAudios(mixed $estado): void
    {
        foreach (Arr::wrap($estado) as $arquivo) {
            if ($arquivo instanceof TemporaryUploadedFile) {
                $arquivo->delete();
            }
        }
    }

    private static function avisarSemTelefone(): void
    {
        Notification::make()
            ->title('Telefone não encontrado')
            ->body('Este interessado não possui um telefone válido cadastrado para contato via WhatsApp.')
            ->danger()
            ->send();
    }

    /**
     * Texto do registro no histórico: diz de onde veio a mensagem (IA, IA ajustada pelo consultor, texto padrão
     * porque a IA falhou ou escrita à mão), para quem lê o histórico (e a própria IA, nas gerações seguintes) não
     * tomar um texto de contingência por uma mensagem personalizada.
     */
    private static function relatoDoHistorico(string $mensagem, string $original, bool $contingencia, string $objetivo, ?string $nomeModelo, int $audios = 0): string
    {
        $detalhe = "Objetivo: {$objetivo}".($nomeModelo ? " com base no modelo '{$nomeModelo}'" : '').($audios > 0 ? ", ouvindo {$audios} áudio(s) da família" : '');

        $ajustada = $mensagem !== $original;

        $origem = match (true) {
            $original === '' => "Mensagem escrita pelo consultor ({$detalhe})",
            $contingencia => 'Mensagem padrão, porque a IA não respondeu'.($ajustada ? ' (ajustada pelo consultor)' : '')." ({$detalhe})",
            $ajustada => "Mensagem redigida pelo Copiloto IA e ajustada pelo consultor ({$detalhe})",
            default => "Mensagem redigida pelo Copiloto IA ({$detalhe})",
        };

        return "💬 {$origem}:\n\n{$mensagem}";
    }
}
