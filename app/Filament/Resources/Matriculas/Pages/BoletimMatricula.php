<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\Nota;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class BoletimMatricula extends Page implements HasSchemas, HasTable
{
    use InteractsWithRecord;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string $resource = MatriculaResource::class;

    protected string $view = 'filament.matriculas.boletim';

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->model($this->record)
            ->components([
                Section::make('Informações da Matrícula')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Placeholder::make('aluno_info')
                            ->label('Nome do Aluno(a)')
                            ->content(fn (?Model $record): string => $record?->pessoa?->nome ?? '-'),
                        Placeholder::make('codigo_info')
                            ->label('Matrícula / RA')
                            ->content(fn (?Model $record): string => $record?->codigo ?? '-'),
                        Placeholder::make('turma_info')
                            ->label('Turma Atual')
                            ->content(fn (?Model $record): string => $record?->turma?->nome ?? '-'),
                        Placeholder::make('curso_info')
                            ->label('Curso / Nível de Ensino')
                            ->content(fn (?Model $record): string => $record?->turma?->serie?->curso?->nome ?? '-'),
                        Placeholder::make('ano_info')
                            ->label('Ano Pedagógico')
                            ->content(fn (?Model $record): string => $record?->turma?->periodoLetivo?->ano ?? now()->year),
                        Placeholder::make('emissao_info')
                            ->label('Data Emissão')
                            ->content(now()->format('d/m/Y')),
                    ])
                    ->columns(['md' => 2, 'default' => 1]),
            ]);
    }

    public function table(Table $table): Table
    {
        $turma = $this->record->turma;
        
        $avaliacoes = Avaliacao::query()
            ->where('turma_id', $turma->id)
            ->with(['categoria', 'etapaAvaliativa'])
            ->get();

        // Identifica as combinações ÚNICAS de Etapa + Categoria presentes na turma
        // Isso garante que as notas do 1º Bimestre não se misturem com as do 2º Bimestre
        $gruposColunas = $avaliacoes->map(fn($av) => [
                'etapa_id' => $av->etapa_avaliativa_id,
                'categoria_avaliacao_id' => $av->categoria_avaliacao_id,
                'nome_etapa' => $av->etapaAvaliativa->nome ?? '',
                'nome_categoria' => $av->categoria->nome ?? '',
                'substitui_id' => $av->categoria?->categoria_avaliacao_substituicao_id,
            ])
            ->unique(fn($i) => $i['etapa_id'] . '-' . $i['categoria_avaliacao_id'])
            ->sortBy(['etapa_id', 'categoria_avaliacao_id']);

        $notasAluno = $this->record->notas()
            ->get()
            ->keyBy('avaliacao_id');

        $notasTurma = Nota::query()
            ->whereHas('matricula', fn ($q) => $q->where('turma_id', $turma->id))
            ->whereNotNull('valor')
            ->get()
            ->groupBy('avaliacao_id');

        $dynamicColumns = [];

        foreach ($gruposColunas as $grupo) {
            $colKey = "et_{$grupo['etapa_id']}_cat_{$grupo['categoria_avaliacao_id']}";
            
            $dynamicColumns[] = TextColumn::make($colKey)
                ->label($grupo['nome_categoria'])
                ->description($grupo['nome_etapa'])
                ->alignCenter()
                ->state(function (Disciplina $record) use ($grupo, $avaliacoes, $notasAluno) {
                    $avs = $avaliacoes->where('disciplina_id', $record->id)
                                    ->where('etapa_avaliativa_id', $grupo['etapa_id'])
                                    ->where('categoria_avaliacao_id', $grupo['categoria_avaliacao_id']);
                    
                    if ($avs->isEmpty()) return '·';
                    
                    $notas = [];
                    foreach ($avs as $av) {
                        $nota = $notasAluno->get($av->id);
                        if ($nota) {
                            $notas[] = number_format((float) $nota->valor, 1, ',', '.');
                        }
                    }

                    return empty($notas) ? '—' : implode(' | ', $notas);
                })
                ->badge()
                ->color(function (Disciplina $record, $state) use ($grupo, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') return 'gray';
                    
                    // Pega a média para decidir a cor
                    $media = $this->getMediaGrupo($grupo, $record->id, $avaliacoes, $notasAluno);
                    if ($media === null) return 'gray';
                    
                    if ($this->isGrupoIgnorado($grupo, $record->id, $avaliacoes, $notasAluno)) {
                        return 'gray';
                    }

                    return $media >= 6.0 ? 'success' : 'danger';
                })
                ->extraAttributes(function (Disciplina $record, $state) use ($grupo, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') return [];
                    
                    if ($this->isGrupoIgnorado($grupo, $record->id, $avaliacoes, $notasAluno)) {
                        return ['class' => 'line-through opacity-50'];
                    }

                    return [];
                })
                ->icon(function (Disciplina $record, $state) use ($grupo, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') return null;
                    
                    return $this->isGrupoIgnorado($grupo, $record->id, $avaliacoes, $notasAluno) 
                        ? 'heroicon-o-exclamation-circle' 
                        : null;
                });
        }

        return $table
            ->query(fn () => Disciplina::query()->whereIn('id', $avaliacoes->pluck('disciplina_id')->unique()->toArray()))
            ->columns([
                TextColumn::make('nome')
                    ->label('Disciplina')
                    ->weight('bold')
                    ->searchable(),
                ...$dynamicColumns,
                TextColumn::make('media_aluno')
                    ->label('Média Aluno')
                    ->alignCenter()
                    ->state(fn (Disciplina $record) => $this->calcularMediaFinalDisciplina($record->id, $avaliacoes, $notasAluno))
                    ->badge()
                    ->color(fn ($state) => $state >= 7 ? 'success' : ($state >= 5 ? 'warning' : 'danger'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', '.')),
                TextColumn::make('media_turma')
                    ->label('Média Turma')
                    ->alignCenter()
                    ->state(fn (Disciplina $record) => $this->calcularMediaTurmaDisciplina($record->id, $avaliacoes, $notasTurma))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', '.')),
            ])
            ->paginated(false);
    }

    private function getMediaGrupo(array $grupo, int $disciplinaId, Collection $avaliacoes, Collection $notasAluno): ?float
    {
        $avs = $avaliacoes->where('disciplina_id', $disciplinaId)
                         ->where('etapa_avaliativa_id', $grupo['etapa_id'])
                         ->where('categoria_avaliacao_id', $grupo['categoria_avaliacao_id']);
        
        $soma = 0; $count = 0;
        foreach ($avs as $av) {
            $nota = $notasAluno->get($av->id);
            if ($nota) { $soma += (float) $nota->valor; $count++; }
        }
        return $count > 0 ? $soma / $count : null;
    }

    private function isGrupoIgnorado(array $grupo, int $disciplinaId, Collection $avaliacoes, Collection $notasAluno): bool
    {
        if (!$grupo['substitui_id']) return false;

        $valorAtual = $this->getMediaGrupo($grupo, $disciplinaId, $avaliacoes, $notasAluno);
        if ($valorAtual === null) return false;

        $grupoSubstituido = [
            'etapa_id' => $grupo['etapa_id'],
            'categoria_avaliacao_id' => $grupo['substitui_id']
        ];
        
        $valorSubstituido = $this->getMediaGrupo($grupoSubstituido, $disciplinaId, $avaliacoes, $notasAluno);
        
        if ($valorSubstituido !== null && $valorAtual > $valorSubstituido) {
            // Este grupo NÃO é ignorado, ele que ignora o outro.
            // Mas o método pergunta "é ignorado?". 
            // Precisamos checar se EXISTE um substituto superior para este grupo.
            return false; 
        }

        // Checar se existe um grupo que SUBSTITUI este grupo e é superior
        $substituto = $avaliacoes->where('disciplina_id', $disciplinaId)
            ->where('etapa_avaliativa_id', $grupo['etapa_id'])
            ->where('categoria.categoria_avaliacao_substituicao_id', $grupo['categoria_avaliacao_id'])
            ->first();
            
        if ($substituto) {
            $mediaSub = $this->getMediaGrupo([
                'etapa_id' => $grupo['etapa_id'],
                'categoria_avaliacao_id' => $substituto->categoria_avaliacao_id
            ], $disciplinaId, $avaliacoes, $notasAluno);
            
            if ($mediaSub !== null && $mediaSub > $valorAtual) {
                return true;
            }
        }

        return false;
    }

    private function calcularMediaFinalDisciplina(int $disciplinaId, Collection $avaliacoes, Collection $notasAluno): ?float
    {
        $avs = $avaliacoes->where('disciplina_id', $disciplinaId);
        if ($avs->isEmpty()) return null;

        $soma = 0; $count = 0;
        // Agrupa por etapa pra calcular médias de bimestres se necessário? 
        // Por enquanto, média simples de todas as avaliações válidas
        
        $totalPonderado = 0; $totalPeso = 0;
        foreach ($avs as $av) {
            $nota = $notasAluno->get($av->id);
            if ($nota) {
                $isIgnorada = $this->isAvaliacaoIgnorada($av, $disciplinaId, $avaliacoes, $notasAluno);
                if (!$isIgnorada) {
                    $peso = (float)($av->nota_maxima ?? 10);
                    $totalPonderado += (float)$nota->valor * $peso;
                    $totalPeso += $peso;
                }
            }
        }
        return $totalPeso > 0 ? $totalPonderado / $totalPeso : null;
    }

    private function isAvaliacaoIgnorada($av, int $disciplinaId, Collection $avaliacoes, Collection $notasAluno): bool
    {
        $notaAtual = $notasAluno->get($av->id);
        if (!$notaAtual) return false;

        // Se esta avaliação tem um substituto na mesma etapa/disciplina que é superior
        $substituto = $avaliacoes->where('disciplina_id', $disciplinaId)
            ->where('etapa_avaliativa_id', $av->etapa_avaliativa_id)
            ->where('categoria.categoria_avaliacao_substituicao_id', $av->categoria_id)
            ->first();

        if ($substituto) {
            $notaSub = $notasAluno->get($substituto->id);
            if ($notaSub && (float)$notaSub->valor > (float)$notaAtual->valor) {
                return true;
            }
        }
        
        // Se esta avaliação é a substituta, mas é inferior à original
        if ($av->categoria?->categoria_avaliacao_substituicao_id) {
            $original = $avaliacoes->where('disciplina_id', $disciplinaId)
                ->where('etapa_avaliativa_id', $av->etapa_avaliativa_id)
                ->where('categoria_id', $av->categoria->categoria_avaliacao_substituicao_id)
                ->first();
            if ($original) {
                $notaOrig = $notasAluno->get($original->id);
                if ($notaOrig && (float)$notaOrig->valor >= (float)$notaAtual->valor) {
                    return true;
                }
            }
        }

        return false;
    }

    private function calcularMediaTurmaDisciplina(int $disciplinaId, Collection $avaliacoes, Collection $notasTurma): ?float
    {
        $matriculaIds = [];
        foreach ($avaliacoes->where('disciplina_id', $disciplinaId) as $av) {
            foreach ($notasTurma->get($av->id, collect()) as $n) {
                $matriculaIds[$n->matricula_id] = true;
            }
        }

        if (empty($matriculaIds)) return null;

        $somaMediasAlunos = 0; $countAlunos = 0;
        foreach (array_keys($matriculaIds) as $mId) {
            $notasDoAluno = collect();
            foreach ($avaliacoes->where('disciplina_id', $disciplinaId) as $av) {
                $nota = $notasTurma->get($av->id, collect())->firstWhere('matricula_id', $mId);
                if ($nota) $notasDoAluno->put($av->id, $nota);
            }
            $media = $this->calcularMediaFinalDisciplina($disciplinaId, $avaliacoes, $notasDoAluno);
            if ($media !== null) { $somaMediasAlunos += $media; $countAlunos++; }
        }
        return $countAlunos > 0 ? $somaMediasAlunos / $countAlunos : null;
    }

    protected static ?string $title = 'Boletim Escolar';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getBreadcrumbs(): array
    {
        return [
            MatriculaResource::getUrl() => 'Matrículas',
            '#' => 'Boletim',
        ];
    }
}
