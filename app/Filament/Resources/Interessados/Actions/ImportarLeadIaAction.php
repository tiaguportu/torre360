<?php

namespace App\Filament\Resources\Interessados\Actions;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\GeminiAgentService;
use App\Services\LeadScoreService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ImportarLeadIaAction
{
    public static function make(?string $name = 'importarComIA'): Action
    {
        return Action::make($name)
            ->label('Importar Lead com IA')
            ->icon('heroicon-o-sparkles')
            ->color('purple')
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
                    ->options(fn () => User::pluck('name', 'id'))
                    ->default(fn () => auth()->id())
                    ->searchable()
                    ->required(),
                Select::make('origem_interessado_id')
                    ->label('Origem Fallback (se a IA não inferir)')
                    ->options(fn () => OrigemInteressado::pluck('nome', 'id'))
                    ->searchable(),
            ])
            ->action(function (array $data) {
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

                    $interessado = static::salvarLeadExtraido(
                        $extracted,
                        (int) $data['usuario_id'],
                        ! empty($data['origem_interessado_id']) ? (int) $data['origem_interessado_id'] : null
                    );

                    Notification::make()
                        ->title('✨ Lead importado com sucesso via IA!')
                        ->body("Interessado **{$interessado->pessoa->nome}** cadastrado com ".$interessado->dependentes()->count().' dependente(s).')
                        ->success()
                        ->actions([
                            Action::make('editar')
                                ->label('Ver/Editar Lead')
                                ->url(InteressadoResource::getUrl('edit', ['record' => $interessado]))
                                ->button(),
                        ])
                        ->send();
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
     * Converte o payload JSON extraído pelo Gemini em registros de banco de dados.
     *
     * @param  array<string, mixed>  $extracted
     */
    public static function salvarLeadExtraido(array $extracted, int $usuarioId, ?int $origemFallbackId = null): Interessado
    {
        $nome = ! empty($extracted['responsavel_nome']) ? trim((string) $extracted['responsavel_nome']) : 'Interessado (Via IA)';
        $email = ! empty($extracted['responsavel_email']) ? strtolower(trim((string) $extracted['responsavel_email'])) : null;
        $telefone = ! empty($extracted['responsavel_telefone']) ? trim((string) $extracted['responsavel_telefone']) : null;
        $cpf = ! empty($extracted['responsavel_cpf']) ? preg_replace('/\D/', '', (string) $extracted['responsavel_cpf']) : null;

        // 1. Localiza ou cria a Pessoa
        $pessoa = null;
        if (! empty($email)) {
            $pessoa = Pessoa::where('email', $email)->first();
        }
        if (! $pessoa && ! empty($telefone)) {
            $pessoa = Pessoa::where('telefone', $telefone)->first();
        }
        if (! $pessoa) {
            $pessoa = Pessoa::create([
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone,
                'cpf' => $cpf ?: null,
            ]);
        }

        // 2. Determina a Origem
        $origemId = $origemFallbackId;
        if (! empty($extracted['origem_sugerida'])) {
            $origemNome = trim((string) $extracted['origem_sugerida']);
            $origem = OrigemInteressado::where('nome', 'LIKE', "%{$origemNome}%")->first();
            if (! $origem) {
                $origem = OrigemInteressado::firstOrCreate(['nome' => ucfirst($origemNome)]);
            }
            $origemId = $origem->id;
        }

        if (! $origemId) {
            $origemId = OrigemInteressado::firstOrCreate(['nome' => 'WhatsApp/IA'])->id;
        }

        // 3. Status padrão "Novo"
        $statusNovo = StatusInteressado::where('nome', 'Novo')->first()
            ?? StatusInteressado::firstOrCreate(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1]);

        $observacoes = [];
        if (! empty($extracted['observacoes'])) {
            $observacoes[] = static::normalizarDatas((string) $extracted['observacoes']);
        }
        $observacoes[] = '✨ Lead importado via IA (Google Gemini) em '.now()->format('d/m/Y H:i').'.';

        $temperatura = isset($extracted['temperatura']) && in_array($extracted['temperatura'], ['quente', 'morno', 'frio'])
            ? $extracted['temperatura']
            : null;

        $valorEstimado = isset($extracted['valor_estimado']) && is_numeric($extracted['valor_estimado'])
            ? (float) $extracted['valor_estimado']
            : null;

        // 4. Cria o Interessado
        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => $usuarioId,
            'origem_interessado_id' => $origemId,
            'status_interessado_id' => $statusNovo->id,
            'temperatura' => $temperatura,
            'valor_estimado' => $valorEstimado,
            'data_proximo_contato' => now()->addDays(1),
            'observacoes' => implode("\n\n", $observacoes),
            'redes_sociais' => static::normalizarRedesSociais($extracted['redes_sociais'] ?? []),
        ]);

        // 5. Cadastra os Alunos / Dependentes
        if (! empty($extracted['alunos']) && is_array($extracted['alunos'])) {
            foreach ($extracted['alunos'] as $alunoData) {
                $nomeAluno = ! empty($alunoData['nome']) ? trim((string) $alunoData['nome']) : null;
                if (empty($nomeAluno)) {
                    continue;
                }

                $serieId = null;
                if (! empty($alunoData['serie_pretendida'])) {
                    $buscaSerie = trim((string) $alunoData['serie_pretendida']);
                    $serie = Serie::where('nome', 'LIKE', "%{$buscaSerie}%")->first();
                    if (! $serie) {
                        $serie = Serie::whereRaw('LOWER(nome) LIKE ?', ['%'.strtolower($buscaSerie).'%'])->first();
                    }
                    $serieId = $serie?->id;
                }

                if (! $serieId) {
                    $serieId = Serie::first()?->id;
                }

                if ($serieId) {
                    InteressadoDependente::create([
                        'interessado_id' => $interessado->id,
                        'nome_crianca' => $nomeAluno,
                        'serie_id' => $serieId,
                        'data_nascimento' => $alunoData['data_nascimento'] ?? null,
                        'vinculo' => $alunoData['vinculo'] ?? 'Filho(a)',
                    ]);
                }
            }
        }

        // 6. Registra o Histórico de Contato com o relato da conversa
        $relato = static::normalizarDatas(! empty($extracted['relato_contato']) ? trim((string) $extracted['relato_contato']) : trim((string) ($extracted['observacoes'] ?? '')));
        if ($relato === '') {
            $relato = 'Contato inicial registrado via importação com IA.';
        }

        $tipoNome = ! empty($extracted['tipo_contato']) ? trim((string) $extracted['tipo_contato']) : 'WhatsApp';
        $tipoContato = TipoContatoInteressado::whereRaw('LOWER(nome) = ?', [mb_strtolower($tipoNome)])->first()
            ?? TipoContatoInteressado::firstOrCreate(['nome' => $tipoNome]);

        try {
            $dataContato = ! empty($extracted['data_contato']) ? Carbon::parse((string) $extracted['data_contato']) : now();
        } catch (\Throwable) {
            $dataContato = now();
        }
        if ($dataContato->isFuture()) {
            $dataContato = now();
        }

        $relato = 'Contato em '.$dataContato->format('d/m/Y H:i').' via '.$tipoContato->nome.".\n".$relato;

        HistoricoContato::create([
            'interessado_id' => $interessado->id,
            'usuario_id' => $usuarioId,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'relato' => $relato,
            'data_contato' => $dataContato,
        ]);

        LeadScoreService::recalcular($interessado);

        return $interessado;
    }

    /**
     * Mantém só redes conhecidas com link http(s) válido; devolve null se não sobrar nenhuma.
     *
     * @return array<int, array{rede: string, url: string}>|null
     */
    public static function normalizarRedesSociais(mixed $redes): ?array
    {
        if (! is_array($redes)) {
            return null;
        }

        $resultado = [];
        foreach ($redes as $item) {
            $url = trim((string) ($item['url'] ?? ''));
            $rede = mb_strtolower(trim((string) ($item['rede'] ?? '')));

            if ($url !== '' && ! preg_match('#^https?://#i', $url) && str_contains($url, '.')) {
                $url = 'https://'.ltrim($url, '/');
            }

            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $resultado[] = [
                'rede' => array_key_exists($rede, Interessado::REDES_SOCIAIS) ? $rede : 'outra',
                'url' => $url,
            ];
        }

        return $resultado === [] ? null : $resultado;
    }

    /**
     * Converte datas em ISO (AAAA-MM-DD) ou com hífen/ponto (DD-MM-AAAA) para DD/MM/AAAA.
     */
    public static function normalizarDatas(string $texto): string
    {
        $texto = preg_replace('/(?<!\d)(\d{4})-(\d{2})-(\d{2})(?!\d)/', '$3/$2/$1', $texto) ?? $texto;

        return preg_replace('/(?<!\d)(\d{2})[-.](\d{2})[-.](\d{4})(?!\d)/', '$1/$2/$3', $texto) ?? $texto;
    }
}
