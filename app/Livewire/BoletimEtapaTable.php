<?php

namespace App\Livewire;

use App\Models\Avaliacao;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\Nota;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Livewire\Component;

class BoletimEtapaTable extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public int $matriculaId;

    public int $etapaId;

    public function table(Table $table): Table
    {
        $matricula = Matricula::find($this->matriculaId);
        $etapa = EtapaAvaliativa::find($this->etapaId);
        $turmaId = $matricula->turma_id;

        $avaliacoes = Avaliacao::query()
            ->where('turma_id', $turmaId)
            ->where('etapa_avaliativa_id', $this->etapaId)
            ->with(['categoria'])
            ->get();

        $categorias = $avaliacoes->map(fn ($av) => $av->categoria)->filter()->unique('id')->sortBy('ordem_boletim');

        $notasAluno = $matricula->notas()->whereNotNull('valor')->get()->keyBy('avaliacao_id');
        $notasTurma = Nota::query()
            ->whereHas('matricula', fn ($q) => $q->where('turma_id', $turmaId))
            ->whereNotNull('valor')
            ->get()
            ->groupBy('avaliacao_id');

        $dynamicColumns = [];

        foreach ($categorias as $categoria) {
            $dynamicColumns[] = TextColumn::make("cat_{$categoria->id}")
                ->label($categoria->nome)
                ->headerTooltip($categoria->descricao)
                ->alignCenter()
                ->state(function (Disciplina $record) use ($categoria, $avaliacoes, $notasAluno) {
                    $mediaCat = $this->getMediaConsolidadaCategoria($categoria->id, $record->id, $avaliacoes, $notasAluno);
                    if ($mediaCat === null) {
                        return $avaliacoes->where('disciplina_id', $record->id)->where('categoria_avaliacao_id', $categoria->id)->isEmpty() ? '·' : '—';
                    }

                    return number_format(round((float) $mediaCat, 2), 1, ',', '.');
                })
                ->color(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') {
                        return 'gray';
                    }
                    $mediaCat = $this->getMediaConsolidadaCategoria($categoria->id, $record->id, $avaliacoes, $notasAluno);
                    if ($mediaCat === null) {
                        return 'gray';
                    }
                    $isIgnorada = $this->isCategoriaIgnorada($categoria->id, $record->id, $avaliacoes, $notasAluno);
                    if ($isIgnorada) {
                        return 'gray';
                    }

                    return $mediaCat >= 7.0 ? 'success' : 'danger';
                })
                ->extraAttributes(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') {
                        return [];
                    }
                    if ($this->isCategoriaIgnorada($categoria->id, $record->id, $avaliacoes, $notasAluno)) {
                        return [
                            'class' => 'line-through opacity-50',
                            'style' => 'text-decoration: line-through !important',
                        ];
                    }

                    return [];
                })
                ->icon(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') {
                        return null;
                    }

                    return $this->isCategoriaIgnorada($categoria->id, $record->id, $avaliacoes, $notasAluno)
                        ? 'heroicon-m-exclamation-circle'
                        : null;
                })
                ->tooltip(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $notasAluno) {
                    $avs = $avaliacoes->where('disciplina_id', $record->id)->where('categoria_avaliacao_id', $categoria->id);
                    $pesos = $avs->map(fn ($av) => 'Peso: '.number_format($av->peso_etapa_avaliativa ?? 1, 1, ',', '.'))->implode(', ');

                    if ($this->isCategoriaIgnorada($categoria->id, $record->id, $avaliacoes, $notasAluno)) {
                        return 'Nota substituída por outra de maior valor em Avaliação substitutiva.'.($pesos ? " ({$pesos})" : '');
                    }

                    return $pesos ?: null;
                });
        }

        return $table
            ->query(Disciplina::query()
                ->whereIn('id', $avaliacoes->pluck('disciplina_id')->unique()->toArray())
                ->orderBy('ordem_boletim')
                ->orderBy('nome'))
            ->heading($etapa->nome)
            ->columns([
                TextColumn::make('nome')
                    ->label('Disciplina')
                    ->weight('bold'),
                ...$dynamicColumns,
                TextColumn::make('media_aluno')
                    ->label('Média Etapa')
                    ->alignCenter()
                    ->state(fn (Disciplina $record) => $this->calcularMediaFinal($record->id, $avaliacoes, $notasAluno))
                    ->color(fn ($state) => $state >= 7 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => number_format(round((float) $state, 2), 1, ',', '.')),
                TextColumn::make('media_turma')
                    ->label('Média Turma')
                    ->alignCenter()
                    ->state(fn (Disciplina $record) => $this->getMediaTurmaEtapa($record->id, $avaliacoes, $notasTurma))
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => number_format(round((float) $state, 2), 1, ',', '.')),
                TextColumn::make('frequencia')
                    ->label('Frequência')
                    ->alignCenter()
                    ->state(fn (Disciplina $record) => $this->getFrequenciaDisciplinaEtapa($record->id, $matricula->id, $turmaId, $etapa))
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 1, ',', '.').'%' : '—')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 75 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    }),
            ])
            ->paginated(false);
    }

    public function calcularMediaFinal(int $disciplinaId, Collection $avaliacoesEtapa, Collection $notasAluno): ?float
    {
        $avs = $avaliacoesEtapa->where('disciplina_id', $disciplinaId);
        if ($avs->isEmpty()) {
            return null;
        }

        $categorias = $avs->map(fn ($av) => $av->categoria)->filter()->unique('id');

        $dadosCategorias = [];
        foreach ($categorias as $cat) {
            $valor = $this->getMediaConsolidadaCategoria($cat->id, $disciplinaId, $avaliacoesEtapa, $notasAluno);
            if ($valor !== null) {
                $dadosCategorias[$cat->id] = [
                    'valor' => $valor,
                    'substitui_id' => $cat->categoria_avaliacao_substituicao_id,
                    'ignorar' => false,
                ];
            }
        }

        foreach ($dadosCategorias as $id => &$item) {
            $cat = $categorias->firstWhere('id', $id);
            $substituidasIds = $cat->substituidas->pluck('id')->toArray();

            if (! empty($substituidasIds)) {
                // Encontrar quais das categorias substituídas estão presentes nestes dados
                $candidatasSubstituicao = [];
                foreach ($substituidasIds as $subId) {
                    if (isset($dadosCategorias[$subId]) && ! $dadosCategorias[$subId]['ignorar']) {
                        $candidatasSubstituicao[$subId] = $dadosCategorias[$subId]['valor'];
                    }
                }

                if (! empty($candidatasSubstituicao)) {
                    // Pegar a de menor valor
                    asort($candidatasSubstituicao);
                    $menorNotaId = array_key_first($candidatasSubstituicao);
                    $menorNotaValor = $candidatasSubstituicao[$menorNotaId];

                    if ($item['valor'] > $menorNotaValor) {
                        $dadosCategorias[$menorNotaId]['ignorar'] = true;
                    } else {
                        $item['ignorar'] = true;
                    }
                }
            }
        }

        $categoriasValidasIds = array_keys(array_filter($dadosCategorias, fn ($i) => ! $i['ignorar']));
        if (empty($categoriasValidasIds)) {
            return null;
        }

        $somaProdutos = 0;
        $somaPesos = 0;
        foreach ($avaliacoesEtapa->where('disciplina_id', $disciplinaId)->whereIn('categoria_avaliacao_id', $categoriasValidasIds) as $av) {
            $nota = $notasAluno->get($av->id);
            if ($nota) {
                $peso = (float) ($av->peso_etapa_avaliativa ?? 1);
                $somaProdutos += (float) $nota->valor * $peso;
                $somaPesos += $peso;
            }
        }

        return $somaPesos > 0 ? $somaProdutos / $somaPesos : null;
    }

    public function getMediaConsolidadaCategoria(int $categoriaId, int $disciplinaId, Collection $avaliacoesEtapa, Collection $notasAluno): ?float
    {
        $avs = $avaliacoesEtapa->where('disciplina_id', $disciplinaId)->where('categoria_avaliacao_id', $categoriaId);
        if ($avs->isEmpty()) {
            return null;
        }

        $somaProdutos = 0;
        $somaPesos = 0;
        foreach ($avs as $av) {
            $nota = $notasAluno->get($av->id);
            if ($nota) {
                $peso = (float) ($av->peso_etapa_avaliativa ?? 1);
                $somaProdutos += (float) $nota->valor * $peso;
                $somaPesos += $peso;
            }
        }

        return $somaPesos > 0 ? $somaProdutos / $somaPesos : null;
    }

    public function isCategoriaIgnorada(int $categoriaId, int $disciplinaId, Collection $avaliacoesEtapa, Collection $notasAluno): bool
    {
        $avs = $avaliacoesEtapa->where('disciplina_id', $disciplinaId);
        $categorias = $avs->map(fn ($av) => $av->categoria)->filter()->unique('id');

        $dados = [];
        foreach ($categorias as $cat) {
            $dados[$cat->id] = [
                'valor' => $this->getMediaConsolidadaCategoria($cat->id, $disciplinaId, $avaliacoesEtapa, $notasAluno),
                'substitui_id' => $cat->categoria_avaliacao_substituicao_id,
            ];
        }

        foreach ($dados as $id => $item) {
            if ($id == $categoriaId && $item['valor'] !== null) {
                // Caso 1: Esta categoria é substituída por alguma outra?
                // Verificamos se alguma das categorias presentes no boletim tem esta categoria na sua lista de 'substituidas'
                foreach ($categorias as $outraCat) {
                    if ($outraCat->id == $id) {
                        continue;
                    }

                    $substituidasPelaOutra = $outraCat->substituidas->pluck('id')->toArray();
                    if (in_array($id, $substituidasPelaOutra)) {
                        $vSub = $this->getMediaConsolidadaCategoria($outraCat->id, $disciplinaId, $avaliacoesEtapa, $notasAluno);
                        if ($vSub !== null) {
                            // Mas só substitui se esta for a MENOR nota entre as que a outraCat substitui
                            $candidatas = [];
                            foreach ($substituidasPelaOutra as $sId) {
                                $vC = $this->getMediaConsolidadaCategoria($sId, $disciplinaId, $avaliacoesEtapa, $notasAluno);
                                if ($vC !== null) {
                                    $candidatas[$sId] = $vC;
                                }
                            }

                            if (! empty($candidatas)) {
                                asort($candidatas);
                                $menorId = array_key_first($candidatas);
                                if ($id == $menorId && $vSub > $item['valor']) {
                                    return true;
                                }
                            }
                        }
                    }
                }

                // Caso 2: Esta categoria é uma substitutiva?
                $catAtual = $categorias->firstWhere('id', $id);
                $substituidasPelaAtual = $catAtual->substituidas->pluck('id')->toArray();
                if (! empty($substituidasPelaAtual)) {
                    $candidatas = [];
                    foreach ($substituidasPelaAtual as $sId) {
                        $vC = $this->getMediaConsolidadaCategoria($sId, $disciplinaId, $avaliacoesEtapa, $notasAluno);
                        if ($vC !== null) {
                            $candidatas[$sId] = $vC;
                        }
                    }

                    if (! empty($candidatas)) {
                        asort($candidatas);
                        $menorValor = reset($candidatas);
                        if ($item['valor'] <= $menorValor) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function getMediaTurmaEtapa(int $disciplinaId, Collection $avaliacoesEtapa, Collection $notasTurma): ?float
    {
        $avs = $avaliacoesEtapa->where('disciplina_id', $disciplinaId);
        $matriculaIds = [];
        foreach ($avs as $av) {
            foreach ($notasTurma->get($av->id, collect()) as $n) {
                $matriculaIds[$n->matricula_id] = true;
            }
        }

        if (empty($matriculaIds)) {
            return null;
        }

        $somaMediasAlunos = 0;
        $countAlunos = 0;
        foreach (array_keys($matriculaIds) as $mId) {
            $notasDoAluno = collect();
            foreach ($avs as $av) {
                $nota = $notasTurma->get($av->id, collect())->firstWhere('matricula_id', $mId);
                if ($nota) {
                    $notasDoAluno->put($av->id, $nota);
                }
            }
            $media = $this->calcularMediaFinal($disciplinaId, $avaliacoesEtapa, $notasDoAluno);
            if ($media !== null) {
                $somaMediasAlunos += $media;
                $countAlunos++;
            }
        }

        return $countAlunos > 0 ? $somaMediasAlunos / $countAlunos : null;
    }

    /**
     * Calcula o percentual de frequência do aluno em uma disciplina dentro do período da etapa.
     * Considera todos os cronogramas de aula da disciplina/turma cuja data está entre
     * data_inicio e data_fim da etapa (inclusive).
     */
    public function getFrequenciaDisciplinaEtapa(int $disciplinaId, int $matriculaId, int $turmaId, EtapaAvaliativa $etapa): ?float
    {
        $dataFimEfetiva = min($etapa->data_fim, now()->toDateString());

        $cronogramas = CronogramaAula::query()
            ->where('turma_id', $turmaId)
            ->where('disciplina_id', $disciplinaId)
            ->whereBetween('data', [$etapa->data_inicio, $dataFimEfetiva])
            ->pluck('id');

        $total = $cronogramas->count();
        if ($total === 0) {
            return null;
        }

        $presencas = FrequenciaEscolar::query()
            ->where('matricula_id', $matriculaId)
            ->whereIn('cronograma_aula_id', $cronogramas)
            ->where('situacao', 'presente')
            ->count();

        return ($presencas / $total) * 100;
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render()
    {
        return view('livewire.boletim-etapa-table');
    }
}
