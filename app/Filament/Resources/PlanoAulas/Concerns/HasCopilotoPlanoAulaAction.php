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
                    ->label('Instruções Pedagógicas Específicas (Opcional)')
                    ->helperText('Ex: foco em dinâmicas em grupo, uso de laboratório, estudo de caso ou debate.')
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
            });
    }
}
