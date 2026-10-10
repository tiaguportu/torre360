<?php

namespace App\Filament\Resources\Interessados\Actions;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\User;
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
    public static function make(?string $name = 'importarComIA'): Action
    {
        return Action::make($name)
            ->label('Importar Lead com IA')
            ->icon('heroicon-o-sparkles')
            ->color('purple')
            // Cria pessoa, lead e dependentes e aciona o Gemini: exige poder cadastrar leads.
            ->authorize(PermissaoAcao::qualquer('Create:Interessado'))
            ->modalHeading('✨ Importar Lead a partir de Mensagem / Print (IA)')
            ->modalDescription('Cole uma mensagem de texto ou anexe uma captura de tela (print de conversa do WhatsApp, Instagram, e-mail). A IA da Google analisará o conteúdo e extrairá os dados automaticamente.')
            ->modalSubmitActionLabel('Analisar e Criar Lead')
            ->form([
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
                    ->helperText('Opcional se você anexou um print acima, ou use para complementar informações.')
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

                if (empty($mensagemBruta) && empty($imagemRelPath)) {
                    Notification::make()
                        ->title('Dados insuficientes')
                        ->body('Por favor, informe uma mensagem de texto ou anexe um print para prosseguir com a extração por IA.')
                        ->warning()
                        ->send();

                    return;
                }

                $imageAbsolutePath = null;
                if ($imagemRelPath) {
                    $imageAbsolutePath = Storage::disk('local')->path($imagemRelPath);
                }

                try {
                    $gemini = app(GeminiAgentService::class);
                    $extracted = $gemini->extrairLead($mensagemBruta, $imageAbsolutePath);

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

                    if ($resultado['avisos'] !== []) {
                        $corpo .= "\n\nConfira: ".implode(' ', $resultado['avisos']);
                    }

                    $notificacao = Notification::make()
                        ->title($resultado['reaproveitado'] ? '✨ Conversa somada ao lead existente' : '✨ Lead importado com sucesso via IA!')
                        ->body($corpo);

                    // Com avisos (série/origem não reconhecida, CPF inválido…) o consultor precisa conferir: tom de atenção.
                    ($resultado['avisos'] === [] ? $notificacao->success() : $notificacao->warning())->send();

                    $livewire->redirect(InteressadoResource::getUrl('edit', ['record' => $interessado]));
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Falha ao Importar Lead com IA')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                } finally {
                    if ($imagemRelPath && Storage::disk('local')->exists($imagemRelPath)) {
                        Storage::disk('local')->delete($imagemRelPath);
                    }
                }
            });
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
