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

        $categorias = $avaliacoes->map(fn($av) => $av->categoria)
            ->filter()
            ->unique('id')
            ->sortBy(function($cat) use ($avaliacoes) {
                $av = $avaliacoes->where('categoria_id', $cat->id)->first();
                return [$av?->etapa_avaliativa_id ?? 0, $cat->id];
            });

        $notasAluno = $this->record->notas()
            ->get()
            ->keyBy('avaliacao_id');

        $notasTurma = Nota::query()
            ->whereHas('matricula', fn ($q) => $q->where('turma_id', $turma->id))
            ->whereNotNull('valor')
            ->get()
            ->groupBy('avaliacao_id');

        $dynamicColumns = [];

        foreach ($categorias as $categoria) {
            $dynamicColumns[] = TextColumn::make("cat_{$categoria->id}")
                ->label($categoria->nome)
                ->alignCenter()
                ->state(function (Disciplina $record) use ($categoria, $avaliacoes, $notasAluno) {
                    $mediaCat = $this->getMediaConsolidadaCategoria($categoria->id, $record->id, $avaliacoes, $notasAluno);
                    
                    if ($mediaCat === null) {
                        return $avaliacoes->where('disciplina_id', $record->id)->where('categoria_id', $categoria->id)->isEmpty() ? '·' : '—';
                    }
                    
                    return number_format($mediaCat, 1, ',', '.');
                })
                ->badge()
                ->color(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') return 'gray';
                    
                    $mediaCat = $this->getMediaConsolidadaCategoria($categoria->id, $record->id, $avaliacoes, $notasAluno);
                    if ($mediaCat === null) return 'gray';
                    
                    $isIgnorada = $this->isCategoriaIgnorada($categoria->id, $record->id, $avaliacoes, $notasAluno);

                    if ($isIgnorada) return 'gray';
                    return $mediaCat >= 6.0 ? 'success' : 'danger';
                })
                ->extraAttributes(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') return [];
                    
                    if ($this->isCategoriaIgnorada($categoria->id, $record->id, $avaliacoes, $notasAluno)) {
                        return ['class' => 'line-through opacity-50'];
                    }

                    return [];
                })
                ->icon(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') return null;
                    
                    return $this->isCategoriaIgnorada($categoria->id, $record->id, $avaliacoes, $notasAluno) 
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

    private function getMediaConsolidadaCategoria(int $categoriaId, int $disciplinaId, Collection $avaliacoes, Collection $notasAluno): ?float
    {
        $avs = $avaliacoes->where('disciplina_id', $disciplinaId)
                         ->where('categoria_id', $categoriaId);
        
        if ($avs->isEmpty()) return null;
        
        $soma = 0;
        $count = 0;
        foreach ($avs as $av) {
            $nota = $notasAluno->get($av->id);
            if ($nota) {
                $soma += (float) $nota->valor;
                $count++;
            }
        }
        
        return $count > 0 ? $soma / $count : null;
    }

    private function isCategoriaIgnorada(int $categoriaId, int $disciplinaId, Collection $avaliacoes, Collection $notasAluno): bool
    {
        $categoriasDaDisciplina = $avaliacoes->where('disciplina_id', $disciplinaId)
            ->map(fn($av) => $av->categoria)->filter()->unique('id');

        $dados = [];
        foreach ($categoriasDaDisciplina as $cat) {
            $dados[$cat->id] = [
                'valor' => $this->getMediaConsolidadaCategoria($cat->id, $disciplinaId, $avaliacoes, $notasAluno),
                'substitui_id' => $cat->categoria_avaliacao_substituicao_id,
                'ignorar' => false,
            ];
        }

        foreach ($dados as $id => &$item) {
            if ($item['substitui_id'] && $item['valor'] !== null) {
                if (isset($dados[$item['substitui_id']]) && $dados[$item['substitui_id']]['valor'] !== null) {
                    if ($item['valor'] > $dados[$item['substitui_id']]['valor']) {
                        $dados[$item['substitui_id']]['ignorar'] = true;
                    } else {
                        $item['ignorar'] = true;
                    }
                }
            }
        }

        return $dados[$categoriaId]['ignorar'] ?? false;
    }

    private function calcularMediaFinalDisciplina(int $disciplinaId, Collection $avaliacoes, Collection $notasAluno): ?float
    {
        $categoriasDaDisciplina = $avaliacoes->where('disciplina_id', $disciplinaId)
            ->map(fn($av) => $av->categoria)->filter()->unique('id');

        $somasCategorias = [];
        foreach ($categoriasDaDisciplina as $cat) {
            $valor = $this->getMediaConsolidadaCategoria($cat->id, $disciplinaId, $avaliacoes, $notasAluno);
            if ($valor !== null) {
                $somasCategorias[$cat->id] = [
                    'valor' => $valor,
                    'substitui_id' => $cat->categoria_avaliacao_substituicao_id,
                    'ignorar' => false
                ];
            }
        }

        foreach ($somasCategorias as $id => &$item) {
            if ($item['substitui_id'] && isset($somasCategorias[$item['substitui_id']])) {
                if ($item['valor'] > $somasCategorias[$item['substitui_id']]['valor']) {
                    $somasCategorias[$item['substitui_id']]['ignorar'] = true;
                } else {
                    $item['ignorar'] = true;
                }
            }
        }

        $validas = array_filter($somasCategorias, fn($i) => !$i['ignorar']);
        
        if (empty($validas)) return null;

        return array_sum(array_column($validas, 'valor')) / count($validas);
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

        $somaMediasAlunos = 0;
        $countAlunos = 0;

        foreach (array_keys($matriculaIds) as $mId) {
            $notasDoAluno = collect();
            foreach ($avaliacoes->where('disciplina_id', $disciplinaId) as $av) {
                $nota = $notasTurma->get($av->id, collect())->firstWhere('matricula_id', $mId);
                if ($nota) $notasDoAluno->put($av->id, $nota);
            }

            $media = $this->calcularMediaFinalDisciplina($disciplinaId, $avaliacoes, $notasDoAluno);
            if ($media !== null) {
                $somaMediasAlunos += $media;
                $countAlunos++;
            }
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
