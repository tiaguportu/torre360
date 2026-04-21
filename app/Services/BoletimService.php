<?php

namespace App\Services;

use App\Models\Avaliacao;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\Nota;
use Illuminate\Support\Collection;

class BoletimService
{
    /**
     * Retorna os dados consolidados do boletim para uma matrícula e etapa(s).
     */
    public function getDadosBoletim(Matricula $matricula, ?int $etapaId = null): array
    {
        $turmaId = $matricula->turma_id;

        $etapasQuery = EtapaAvaliativa::query();
        if ($etapaId) {
            $etapasQuery->where('id', $etapaId);
        } else {
            // Se não informou etapa, pega apenas as que possuem notas vinculadas ao aluno
            $etapaIdsComNotas = Avaliacao::query()
                ->whereHas('notas', fn ($q) => $q->where('matricula_id', $matricula->id)->whereNotNull('valor'))
                ->where('turma_id', $turmaId)
                ->pluck('etapa_avaliativa_id')
                ->unique();

            $etapasQuery->whereIn('id', $etapaIdsComNotas);
        }

        $etapas = $etapasQuery->orderBy('id')->get();
        $resultado = [];

        $notasAluno = $matricula->notas()->whereNotNull('valor')->get()->keyBy('avaliacao_id');
        $notasTurma = Nota::query()
            ->whereHas('matricula', fn ($q) => $q->where('turma_id', $turmaId))
            ->whereNotNull('valor')
            ->get()
            ->groupBy('avaliacao_id');

        foreach ($etapas as $etapa) {
            $avaliacoes = Avaliacao::query()
                ->where('turma_id', $turmaId)
                ->where('etapa_avaliativa_id', $etapa->id)
                ->with(['categoria'])
                ->get();

            $categorias = $avaliacoes->map(fn ($av) => $av->categoria)
                ->filter()
                ->unique('id')
                ->sortBy('ordem_boletim');

            $disciplinasIds = $avaliacoes->pluck('disciplina_id')->unique();
            $disciplinas = Disciplina::query()
                ->whereIn('id', $disciplinasIds)
                ->orderBy('ordem_boletim')
                ->orderBy('nome')
                ->get();

            $linhas = [];
            foreach ($disciplinas as $disciplina) {
                $categsDados = [];
                foreach ($categorias as $categoria) {
                    $mediaCat = $this->getMediaConsolidadaCategoria($categoria->id, $disciplina->id, $avaliacoes, $notasAluno);
                    $categsDados[$categoria->id] = [
                        'valor' => $mediaCat,
                        'is_ignorada' => $mediaCat !== null ? $this->isCategoriaIgnorada($categoria->id, $disciplina->id, $avaliacoes, $notasAluno) : false,
                        'ausente' => $avaliacoes->where('disciplina_id', $disciplina->id)->where('categoria_avaliacao_id', $categoria->id)->isEmpty(),
                    ];
                }

                $linhas[] = [
                    'disciplina' => $disciplina,
                    'categorias' => $categsDados,
                    'media_final' => $this->calcularMediaFinal($disciplina->id, $avaliacoes, $notasAluno),
                    'media_turma' => $this->getMediaTurmaEtapa($disciplina->id, $avaliacoes, $notasTurma),
                    'frequencia' => $this->getFrequenciaDisciplinaEtapa($disciplina->id, $matricula->id, $turmaId, $etapa),
                ];
            }

            $resultado[] = [
                'etapa' => $etapa,
                'categorias' => $categorias,
                'linhas' => $linhas,
            ];
        }

        return [
            'matricula' => $matricula->load(['pessoa', 'turma.serie.curso', 'turma.periodoLetivo']),
            'etapas' => $resultado,
        ];
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
                $candidatasSubstituicao = [];
                foreach ($substituidasIds as $subId) {
                    if (isset($dadosCategorias[$subId]) && ! $dadosCategorias[$subId]['ignorar']) {
                        $candidatasSubstituicao[$subId] = $dadosCategorias[$subId]['valor'];
                    }
                }

                if (! empty($candidatasSubstituicao)) {
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
                foreach ($categorias as $outraCat) {
                    if ($outraCat->id == $id) {
                        continue;
                    }

                    $substituidasPelaOutra = $outraCat->substituidas->pluck('id')->toArray();
                    if (in_array($id, $substituidasPelaOutra)) {
                        $vSub = $this->getMediaConsolidadaCategoria($outraCat->id, $disciplinaId, $avaliacoesEtapa, $notasAluno);
                        if ($vSub !== null) {
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
}
