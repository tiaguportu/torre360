<?php

namespace App\Livewire;

use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Services\BoletimService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
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

        $boletimService = app(BoletimService::class);
        $dados = $boletimService->getDadosBoletim($matricula, $this->etapaId);

        // No caso do componente Livewire, ele recebe apenas uma etapa por vez pelo seu próprio escopo
        $dadosEtapa = $dados['etapas'][0] ?? null;

        if (! $dadosEtapa) {
            return $table->query(Disciplina::query()->whereRaw('1=0'));
        }

        $categorias = $dadosEtapa['categorias'];
        $linhas = collect($dadosEtapa['linhas']);
        $disciplinasIds = $linhas->pluck('disciplina.id')->toArray();

        $dynamicColumns = [];

        foreach ($categorias as $categoria) {
            $dynamicColumns[] = TextColumn::make("cat_{$categoria->id}")
                ->label($categoria->nome)
                ->headerTooltip($categoria->descricao)
                ->alignCenter()
                ->state(function (Disciplina $record) use ($categoria, $linhas) {
                    $dadosLinha = $linhas->firstWhere('disciplina.id', $record->id);
                    $mediaCat = $dadosLinha['categorias'][$categoria->id]['valor'] ?? null;

                    if ($mediaCat === null) {
                        return ($dadosLinha['categorias'][$categoria->id]['ausente'] ?? true) ? '·' : '—';
                    }

                    return number_format(round((float) $mediaCat, 2), 1, ',', '.');
                })
                ->color(function (Disciplina $record, $state) use ($categoria, $linhas) {
                    if ($state === '·' || $state === '—') {
                        return 'gray';
                    }
                    $dadosLinha = $linhas->firstWhere('disciplina.id', $record->id);
                    $mediaCat = $dadosLinha['categorias'][$categoria->id]['valor'] ?? null;
                    $isIgnorada = $dadosLinha['categorias'][$categoria->id]['is_ignorada'] ?? false;

                    if ($mediaCat === null || $isIgnorada) {
                        return 'gray';
                    }

                    return $mediaCat >= 7.0 ? 'success' : 'danger';
                })
                ->extraAttributes(function (Disciplina $record, $state) use ($categoria, $linhas) {
                    if ($state === '·' || $state === '—') {
                        return [];
                    }
                    $dadosLinha = $linhas->firstWhere('disciplina.id', $record->id);
                    if ($dadosLinha['categorias'][$categoria->id]['is_ignorada'] ?? false) {
                        return [
                            'class' => 'line-through opacity-50',
                            'style' => 'text-decoration: line-through !important',
                        ];
                    }

                    return [];
                })
                ->icon(function (Disciplina $record, $state) use ($categoria, $linhas) {
                    if ($state === '·' || $state === '—') {
                        return null;
                    }
                    $dadosLinha = $linhas->firstWhere('disciplina.id', $record->id);

                    return ($dadosLinha['categorias'][$categoria->id]['is_ignorada'] ?? false)
                        ? 'heroicon-m-exclamation-circle'
                        : null;
                })
                ->tooltip(function (Disciplina $record, $state) use ($categoria, $linhas) {
                    $dadosLinha = $linhas->firstWhere('disciplina.id', $record->id);
                    if ($dadosLinha['categorias'][$categoria->id]['is_ignorada'] ?? false) {
                        return 'Nota substituída por outra de maior valor em Avaliação substitutiva.';
                    }

                    return null;
                });
        }

        return $table
            ->query(Disciplina::query()
                ->whereIn('id', $disciplinasIds)
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
                    ->state(fn (Disciplina $record) => $linhas->firstWhere('disciplina.id', $record->id)['media_final'] ?? null)
                    ->color(fn ($state) => $state >= 7 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format(round((float) $state, 2), 1, ',', '.') : '—'),
                TextColumn::make('media_turma')
                    ->label('Média Turma')
                    ->alignCenter()
                    ->state(fn (Disciplina $record) => $linhas->firstWhere('disciplina.id', $record->id)['media_turma'] ?? null)
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format(round((float) $state, 2), 1, ',', '.') : '—'),
                TextColumn::make('frequencia')
                    ->label('Frequência')
                    ->alignCenter()
                    ->state(fn (Disciplina $record) => $linhas->firstWhere('disciplina.id', $record->id)['frequencia'] ?? null)
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

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render()
    {
        return view('livewire.boletim-etapa-table');
    }
}
