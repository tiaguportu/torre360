<?php

namespace App\Filament\Resources\Interessados\Actions;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Services\GeminiAgentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class ImportarLeadIaAction
{
    public static function make(?string $name = 'importarComIA'): Action
    {
        return Action::make($name)
            ->label('Importar Lead com IA')
            ->icon('heroicon-o-sparkles')
            ->color('purple')
            ->modalHeading('✨ Importar Lead a partir de Mensagem (IA)')
            ->modalDescription('Cole a mensagem bruta recebida (WhatsApp, e-mail, transcrição ou anotação). A IA da Google extrairá automaticamente os dados do responsável e dos alunos.')
            ->modalSubmitActionLabel('Analisar e Criar Lead')
            ->form([
                Textarea::make('mensagem_bruta')
                    ->label('Mensagem Bruta / Texto do Cliente')
                    ->placeholder("Exemplo:\nOlá! Meu nome é Carla Souza, telefone (11) 98888-5555, e-mail carla@gmail.com. Gostaria de saber informações sobre o 3º ano do Ensino Fundamental para o meu filho Lucas de 8 anos.")
                    ->rows(6)
                    ->required(),
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
                try {
                    $gemini = app(GeminiAgentService::class);
                    $extracted = $gemini->extrairLeadDeTexto($data['mensagem_bruta']);

                    $interessado = static::salvarLeadExtraido($extracted, (int) $data['usuario_id'], $data['origem_interessado_id'] ? (int) $data['origem_interessado_id'] : null);

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
            $observacoes[] = (string) $extracted['observacoes'];
        }
        $observacoes[] = '✨ Lead importado via IA (Google Gemini).';

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

        return $interessado;
    }
}
