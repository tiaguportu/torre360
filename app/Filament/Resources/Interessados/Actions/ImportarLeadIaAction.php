<?php

namespace App\Filament\Resources\Interessados\Actions;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\User;
use App\Services\ConversaWhatsappZipService;
use App\Services\GeminiAgentService;
use App\Services\ImportacaoLeadIaService;
use App\Support\PermissaoAcao;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ImportarLeadIaAction
{
    /**
     * Teto do .zip exportado (KB). Só o texto e algumas mídias são usadas, mas a exportação com mídia pode ser
     * grande. Atenção: o gate de upload do Livewire (`config/livewire.php`) precisa ser pelo menos este valor.
     */
    private const ZIP_KB_MAXIMOS = 30720;

    public static function make(?string $name = 'importarComIA'): Action
    {
        return Action::make($name)
            ->label('Importar Lead com IA')
            ->icon('heroicon-o-sparkles')
            ->color('purple')
            // Cria pessoa, lead e dependentes e aciona o Gemini: exige poder cadastrar leads.
            ->authorize(PermissaoAcao::qualquer('Create:Interessado'))
            ->modalHeading('✨ Importar Lead a partir de Mensagem / Print / Conversa (IA)')
            ->modalDescription('Cole uma mensagem de texto, anexe uma captura de tela (print de conversa do WhatsApp, Instagram, e-mail) ou envie o .zip exportado pelo WhatsApp (texto, áudios e imagens). A IA da Google analisará o conteúdo e extrairá os dados automaticamente.')
            ->modalSubmitActionLabel('Analisar e Criar Lead')
            ->form([
                FileUpload::make('conversa_zip')
                    ->label('Conversa exportada do WhatsApp (.zip)')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'application/x-zip', 'multipart/x-zip'])
                    ->maxSize(self::ZIP_KB_MAXIMOS)
                    ->disk('local')
                    ->directory('temp-lead-zips')
                    ->helperText('No WhatsApp: abra a conversa → ⋮ → Mais → Exportar conversa → Incluir mídia, e anexe o .zip gerado. A IA lê o texto, ouve até '
                        .ConversaWhatsappZipService::MAX_AUDIOS.' áudios e analisa até '.ConversaWhatsappZipService::MAX_IMAGENS.' imagens, na ordem da conversa; vídeos, documentos e figurinhas são ignorados.')
                    ->columnSpanFull(),
                FileUpload::make('imagem_print')
                    ->label('Print / Captura de Tela da Conversa')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/webp'])
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('temp-lead-prints')
                    ->helperText('Anexe uma imagem com o print da conversa (WhatsApp, Instagram, e-mail, etc.).')
                    ->columnSpanFull(),
                Textarea::make('mensagem_bruta')
                    ->label('Mensagem Bruta / Texto Adicional')
                    ->placeholder("Exemplo:\nOlá! Meu nome é Carla Souza, telefone (11) 98888-5555, e-mail carla@gmail.com. Gostaria de saber informações sobre o 3º ano do Ensino Fundamental para o meu filho Lucas de 8 anos.")
                    ->rows(4)
                    ->helperText('Opcional se você anexou o .zip ou um print acima, ou use para complementar informações.')
                    ->columnSpanFull(),
                Select::make('usuario_id')
                    ->label('Consultor Responsável')
                    ->options(fn () => User::consultoresCrm()->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => auth()->id())
                    ->searchable()
                    ->required(),
                Select::make('origem_interessado_id')
                    ->label('Origem Fallback (se a IA não inferir)')
                    ->options(fn () => OrigemInteressado::pluck('nome', 'id'))
                    ->searchable(),
            ])
            ->action(function (array $data, $livewire) {
                $mensagemBruta = ! empty($data['mensagem_bruta']) ? trim((string) $data['mensagem_bruta']) : null;
                $imagemRelPath = ! empty($data['imagem_print']) ? $data['imagem_print'] : null;
                $zipRelPath = ! empty($data['conversa_zip']) ? $data['conversa_zip'] : null;

                if (empty($mensagemBruta) && empty($imagemRelPath) && empty($zipRelPath)) {
                    Notification::make()
                        ->title('Dados insuficientes')
                        ->body('Por favor, informe uma mensagem de texto, anexe um print ou envie o .zip da conversa do WhatsApp para prosseguir com a extração por IA.')
                        ->warning()
                        ->send();

                    return;
                }

                $imageAbsolutePath = null;
                if ($imagemRelPath) {
                    $imageAbsolutePath = Storage::disk('local')->path($imagemRelPath);
                }

                $conversa = null;

                try {
                    if ($zipRelPath) {
                        // O print segue na mesma requisição ao Gemini: o que ele ocupa sai do teto das mídias do .zip.
                        $conversa = app(ConversaWhatsappZipService::class)->ler(
                            Storage::disk('local')->path($zipRelPath),
                            $imageAbsolutePath ? (int) @filesize($imageAbsolutePath) : 0
                        );

                        // Ouvir os áudios leva mais que o limite padrão de 30 s do PHP numa requisição web.
                        if (! app()->runningUnitTests() && collect($conversa['midias'])->contains('tipo', 'audio')) {
                            @set_time_limit(180);
                        }
                    }

                    $gemini = app(GeminiAgentService::class);
                    $extracted = $gemini->extrairLead(
                        self::textoParaIa($mensagemBruta, $conversa['texto'] ?? null),
                        $imageAbsolutePath,
                        null,
                        $conversa['midias'] ?? []
                    );

                    $resultado = app(ImportacaoLeadIaService::class)->importar(
                        $extracted,
                        (int) $data['usuario_id'],
                        ! empty($data['origem_interessado_id']) ? (int) $data['origem_interessado_id'] : null
                    );

                    /** @var Interessado $interessado */
                    $interessado = $resultado['interessado'];

                    $corpo = $resultado['reaproveitado']
                        ? "{$interessado->pessoa->nome} já tinha um lead ativo: a conversa foi somada ao histórico dele, sem criar outro."
                        : "Interessado {$interessado->pessoa->nome} cadastrado com ".$interessado->dependentes()->count().' dependente(s).';

                    $avisos = array_merge($resultado['avisos'], $conversa['avisos'] ?? []);

                    if ($conversa !== null) {
                        $analisadas = ConversaWhatsappZipService::descrever($conversa['totais']);
                        $corpo .= "\n\nConversa do WhatsApp lida".($analisadas !== '' ? " junto com {$analisadas}." : ' (só o texto).');
                    }

                    if ($avisos !== []) {
                        $corpo .= "\n\nConfira: ".implode(' ', $avisos);
                    }

                    $notificacao = Notification::make()
                        ->title($resultado['reaproveitado'] ? '✨ Conversa somada ao lead existente' : '✨ Lead importado com sucesso via IA!')
                        ->body($corpo);

                    // Com avisos (série/origem não reconhecida, CPF inválido, mídia que ficou de fora…) o consultor precisa conferir: tom de atenção.
                    ($avisos === [] ? $notificacao->success() : $notificacao->warning())->send();

                    $livewire->redirect(InteressadoResource::getUrl('edit', ['record' => $interessado]));
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Falha ao Importar Lead com IA')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                } finally {
                    // Print, .zip e mídias extraídas são temporários: nada disso fica no disco depois da análise.
                    foreach (array_filter([$imagemRelPath, $zipRelPath]) as $temporario) {
                        if (Storage::disk('local')->exists($temporario)) {
                            Storage::disk('local')->delete($temporario);
                        }
                    }

                    if ($conversa !== null) {
                        Storage::disk('local')->deleteDirectory($conversa['diretorio']);
                    }
                }
            });
    }

    /**
     * Texto enviado à IA: o que o consultor colou e a conversa do .zip. Juntos, o que foi colado vira observação
     * (a conversa já abre com o próprio cabeçalho).
     */
    private static function textoParaIa(?string $mensagemBruta, ?string $textoDaConversa): ?string
    {
        if ($textoDaConversa === null) {
            return $mensagemBruta;
        }

        return $mensagemBruta === null || $mensagemBruta === ''
            ? $textoDaConversa
            : "Observações do consultor:\n{$mensagemBruta}\n\n{$textoDaConversa}";
    }

    /**
     * Converte o payload JSON extraído pelo Gemini em registros de banco de dados (ver `ImportacaoLeadIaService`).
     *
     * @param  array<string, mixed>  $extracted
     */
    public static function salvarLeadExtraido(array $extracted, int $usuarioId, ?int $origemFallbackId = null): Interessado
    {
        return app(ImportacaoLeadIaService::class)->importar($extracted, $usuarioId, $origemFallbackId)['interessado'];
    }
}
