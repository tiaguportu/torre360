<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoAulas\Concerns;

use App\Models\Disciplina;
use App\Models\Habilidade;
use App\Models\Turma;
use App\Services\CopilotoPedagogico\GerarPlanoAulaInput;
use App\Services\CopilotoPedagogicoIaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

trait HasCopilotoPlanoAulaAction
{
    protected function getCopilotoPlanoAulaAction(): Action
    {
        return Action::make('copilotoIa')
            ->label('Criar com Copiloto IA')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->visible(function (): bool {
                $user = auth()->user();
                if (! $user?->can('GerarComCopiloto:PlanoAula')) {
                    return false;
                }

                if (isset($this->record) && method_exists($this->record, 'foiExecutado') && $this->record->foiExecutado()) {
                    return false;
                }

                return true;
            })
            ->modalHeading('Copiloto Pedagógico IA: Planejamento Alinhado à BNCC')
            ->modalDescription('Defina a turma, disciplina e habilidades da BNCC. A IA gerará a proposta didática estruturada preenchendo o formulário para sua revisão.')
            ->schema([
                Select::make('escopo_geracao')
                    ->label('O que você deseja fazer?')
                    ->options([
                        'completo' => 'Gerar Plano de Aula Completo (todas as seções)',
                        'objetivos' => 'Regenerar apenas: Objetivos de Aprendizagem',
                        'metodologia' => 'Regenerar apenas: Metodologia e Cronograma',
                        'recursos' => 'Regenerar apenas: Recursos Didáticos',
                        'avaliacao' => 'Regenerar apenas: Avaliação Formativa',
                    ])
                    ->default('completo')
                    ->required()
                    ->live(),

                TextInput::make('instrucao_ajuste')
                    ->label('Instrução Curta de Ajuste para esta Seção (Opcional)')
                    ->helperText('Ex: foco em dinâmicas práticas, apoio de tecnologia ou autoavaliação.')
                    ->visible(fn (Get $get): bool => $get('escopo_geracao') !== 'completo')
                    ->maxLength(250),

                Select::make('turma_id')
                    ->label('Turma')
                    ->options(fn () => Turma::vigentes()->pluck('nome', 'id'))
                    ->default(fn () => $this->record?->turma_id ?? $this->data['turma_id'] ?? null)
                    ->searchable()
                    ->required()
                    ->live(),

                Select::make('disciplina_id')
                    ->label('Disciplina')
                    ->options(function (Get $get) {
                        $turmaId = $get('turma_id') ?? $this->record?->turma_id ?? null;
                        $turma = $turmaId ? Turma::find($turmaId) : null;

                        return $turma?->disciplinas->pluck('nome', 'id') ?? [];
                    })
                    ->default(fn () => $this->record?->disciplina_id ?? $this->data['disciplina_id'] ?? null)
                    ->searchable()
                    ->required(),

                Select::make('habilidades')
                    ->label('Habilidades da BNCC')
                    ->options(fn () => Habilidade::query()->limit(150)->get()->mapWithKeys(fn ($h) => [$h->id => ($h->codigo ? "[{$h->codigo}] " : '').$h->nome]))
                    ->default(fn () => isset($this->record) ? $this->record->habilidades->pluck('id')->all() : null)
                    ->multiple()
                    ->searchable()
                    ->required(),

                TextInput::make('numero_aulas')
                    ->label('Qtd. de Aulas')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),

                TextInput::make('duracao_minutos_por_aula')
                    ->label('Duração por Aula (min)')
                    ->numeric()
                    ->default(50)
                    ->minValue(10)
                    ->suffix('min')
                    ->required(),

                Textarea::make('instrucoes_extras')
                    ->label('Instruções Pedagógicas Gerais (Opcional)')
                    ->helperText('Ex: foco em dinâmicas em grupo, uso de laboratório, estudo de caso ou debate.')
                    ->visible(fn (Get $get): bool => $get('escopo_geracao') === 'completo')
                    ->rows(2),
            ])
            ->action(function (array $data): void {
                $turma = Turma::with('serie')->findOrFail((int) $data['turma_id']);
                $disciplina = Disciplina::findOrFail((int) $data['disciplina_id']);
                $habilidades = Habilidade::whereIn('id', (array) $data['habilidades'])->get()->map(fn ($h) => [
                    'id' => $h->id,
                    'codigo' => $h->codigo ?? 'BNCC',
                    'nome' => $h->nome ?? '',
                    'descricao' => $h->descricao ?? '',
                ])->all();

                $input = new GerarPlanoAulaInput(
                    turmaId: $turma->id,
                    disciplinaId: $disciplina->id,
                    turmaNome: $turma->nome,
                    serieNome: $turma->serie?->nome ?? 'Ensino Fundamental',
                    disciplinaNome: $disciplina->nome,
                    numeroAulas: (int) $data['numero_aulas'],
                    duracaoMinutosPorAula: (int) $data['duracao_minutos_por_aula'],
                    habilidades: $habilidades,
                    instrucoesExtras: $data['instrucoes_extras'] ?? null,
                );

                $copilotoService = app(CopilotoPedagogicoIaService::class);
                $escopo = (string) ($data['escopo_geracao'] ?? 'completo');

                if ($escopo === 'completo') {
                    $output = $copilotoService->gerarPlanoAula($input, auth()->user());

                    // Preenche o formulário local sem salvar no banco de dados
                    $this->form->fill(array_merge($this->data ?? [], [
                        'turma_id' => $turma->id,
                        'disciplina_id' => $disciplina->id,
                        'objetivos' => $output->toObjetivosTexto(),
                        'metodologia' => $output->toMetodologiaTexto(),
                        'recursos' => $output->toRecursosTexto(),
                        'avaliacao' => $output->toAvaliacaoTexto(),
                        'habilidades' => $output->habilidadeIds,
                    ]));

                    Notification::make()
                        ->success()
                        ->title('Sugestão do Copiloto IA aplicada!')
                        ->body('Os campos foram preenchidos com objetivos e cronograma. Revise o conteúdo antes de salvar.')
                        ->send();
                } else {
                    $chaveSessao = 'copiloto_regenerar_sessao_'.auth()->id();
                    $tentativas = (int) session()->get($chaveSessao, 0);

                    if ($tentativas >= 10) {
                        Notification::make()
                            ->warning()
                            ->title('Limite de regenerações atingido')
                            ->body('Você atingiu o limite de 10 regenerações por sessão. Salve o plano antes de continuar.')
                            ->send();

                        return;
                    }

                    session()->put($chaveSessao, $tentativas + 1);

                    $instrucaoAjuste = ! empty($data['instrucao_ajuste']) ? (string) $data['instrucao_ajuste'] : null;
                    $textoRegenerado = $copilotoService->regenerarSecaoPlanoAula(
                        input: $input,
                        secao: $escopo,
                        instrucaoCurta: $instrucaoAjuste,
                        user: auth()->user()
                    );

                    $this->form->fill(array_merge($this->data ?? [], [
                        $escopo => $textoRegenerado,
                    ]));

                    $nomesSecoes = [
                        'objetivos' => 'Objetivos de Aprendizagem',
                        'metodologia' => 'Metodologia e Cronograma',
                        'recursos' => 'Recursos Didáticos',
                        'avaliacao' => 'Avaliação Formativa',
                    ];

                    $nomeSecao = $nomesSecoes[$escopo] ?? $escopo;

                    Notification::make()
                        ->success()
                        ->title("Seção '{$nomeSecao}' regenerada!")
                        ->body('A nova sugestão foi aplicada no formulário. Revise o conteúdo antes de salvar.')
                        ->send();
                }
            });
    }
}
