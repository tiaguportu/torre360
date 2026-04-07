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

        // Identifica as Categorias de Avaliação ÚNICAS presentes na turma
        $categorias = $avaliacoes->map(fn($av) => $av->categoria)
            ->filter()
            ->unique('id')
            ->sortBy(function($cat) use ($avaliacoes) {
                // Ordena as categorias pela primeira aparição de etapa avaliativa
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
                    // Encontra a avaliação desta disciplina que pertence a esta categoria
                    $av = $avaliacoes->where('disciplina_id', $record->id)
                                    ->where('categoria_id', $categoria->id)
                                    ->first();
                    
                    if (!$av) {
                        return '·';
                    }
                    
                    $nota = $notasAluno->get($av->id);

                    return $nota ? number_format((float) $nota->valor, 1, ',', '.') : '—';
                })
                ->badge()
                ->color(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') {
                        return 'gray';
                    }
                    
                    $av = $avaliacoes->where('disciplina_id', $record->id)
                                    ->where('categoria_id', $categoria->id)
                                    ->first();
                    if (!$av) {
                        return 'gray';
                    }
                    
                    $nota = $notasAluno->get($av->id);
                    if (! $nota) {
                        return 'gray';
                    }

                    $percentual = ((float) $nota->valor / (float) ($av->nota_maxima ?? 10)) * 100;
                    $isIgnorada = $this->isNotaIgnorada($av->id, $record->id, $notasAluno, $av->turma_id);

                    if ($isIgnorada) {
                        return 'gray';
                    }

                    return $percentual >= 60 ? 'success' : 'danger';
                })
                ->extraAttributes(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') {
                        return [];
                    }
                    
                    $av = $avaliacoes->where('disciplina_id', $record->id)
                                    ->where('categoria_id', $categoria->id)
                                    ->first();
                    if (!$av) {
                        return [];
                    }
                    
                    $isIgnorada = $this->isNotaIgnorada($av->id, $record->id, $notasAluno, $av->turma_id);
                    if ($isIgnorada) {
                        return ['class' => 'line-through opacity-50'];
                    }

                    return [];
                })
                ->icon(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $notasAluno) {
                    if ($state === '·' || $state === '—') {
                        return null;
                    }
                    
                    $av = $avaliacoes->where('disciplina_id', $record->id)
                                    ->where('categoria_id', $categoria->id)
                                    ->first();
                    if (!$av) {
                        return null;
                    }

                    $isIgnorada = $this->isNotaIgnorada($av->id, $record->id, $notasAluno, $av->turma_id);

                    return $isIgnorada ? 'heroicon-o-exclamation-circle' : null;
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
                    ->state(fn (Disciplina $record) => $this->getMediaAlunoPorDisciplina($record->id, $notasAluno, $this->record->turma_id))
                    ->badge()
                    ->color(fn ($state) => $state >= 7 ? 'success' : ($state >= 5 ? 'warning' : 'danger'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', '.')),
                TextColumn::make('media_turma')
                    ->label('Média Turma')
                    ->alignCenter()
                    ->state(fn (Disciplina $record) => $this->getMediaTurmaPorDisciplina($record->id, $notasTurma, $this->record->turma_id))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', '.')),
            ])
            ->paginated(false);
    }

    private function isNotaIgnorada(int $avId, int $disciplinaId, Collection $notasAluno, int $turmaId): bool
    {
        $avs = Avaliacao::where('turma_id', $turmaId)->where('disciplina_id', $disciplinaId)->with('categoria')->get();
        $dados = [];
        foreach ($avs as $av) {
            $nota = $notasAluno->get($av->id);
            $dados[$av->id] = [
                'valor' => $nota ? (float) $nota->valor : null,
                'categoria_id' => $av->categoria_id,
                'substitui_id' => $av->categoria?->categoria_avaliacao_substituicao_id,
                'ignorar' => false,
            ];
        }
        foreach ($dados as $id => &$item) {
            if ($item['substitui_id'] && $item['valor'] !== null) {
                foreach ($dados as $outroId => &$outroItem) {
                    if ($outroId !== $id && $outroItem['categoria_id'] == $item['substitui_id'] && $outroItem['valor'] !== null) {
                        if ($item['valor'] > $outroItem['valor']) {
                            $outroItem['ignorar'] = true;
                        } else {
                            $item['ignorar'] = true;
                        }
                    }
                }
            }
        }

        return $dados[$avId]['ignorar'] ?? false;
    }

    private function getMediaAlunoPorDisciplina(int $disciplinaId, Collection $notasAluno, int $turmaId): ?float
    {
        $avs = Avaliacao::where('turma_id', $turmaId)->where('disciplina_id', $disciplinaId)->with('categoria')->get();

        return app(\App\Filament\Schemas\Components\BoletimeGradesTable::class)->getMediaAlunoPorDisciplina($disciplinaId, $notasAluno, collect([$disciplinaId => $avs]));
    }

    private function getMediaTurmaPorDisciplina(int $disciplinaId, Collection $notasTurma, int $turmaId): ?float
    {
        $avs = Avaliacao::where('turma_id', $turmaId)->where('disciplina_id', $disciplinaId)->with('categoria')->get();

        return app(\App\Filament\Schemas\Components\BoletimeGradesTable::class)->getMediaTurmaPorDisciplina($disciplinaId, $notasTurma, collect([$disciplinaId => $avs]));
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
