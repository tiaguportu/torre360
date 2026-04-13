<?php

namespace App\Livewire;

use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class BoletimEdicaoEtapaTable extends Component implements HasActions, HasForms, HasTable
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

        $categorias = $avaliacoes->map(fn ($av) => $av->categoria)->filter()->unique('id')->sortBy('ordem');
        $disciplinasIds = $avaliacoes->pluck('disciplina_id')->unique()->toArray();

        $dynamicColumns = [];

        foreach ($categorias as $categoria) {
            $dynamicColumns[] = TextInputColumn::make("cat_{$categoria->id}")
                ->label($categoria->nome)
                ->headerTooltip($categoria->descricao)
                ->alignCenter()
                ->state(function (Disciplina $record) use ($categoria, $avaliacoes, $matricula) {
                    $avaliacao = $avaliacoes->where('disciplina_id', $record->id)->where('categoria_avaliacao_id', $categoria->id)->first();
                    if (! $avaliacao) {
                        return null;
                    }

                    $nota = Nota::where('avaliacao_id', $avaliacao->id)
                        ->where('matricula_id', $matricula->id)
                        ->first();

                    return $nota?->valor;
                })
                ->updateStateUsing(function (Disciplina $record, $state) use ($categoria, $avaliacoes, $matricula) {
                    $avaliacao = $avaliacoes->where('disciplina_id', $record->id)->where('categoria_avaliacao_id', $categoria->id)->first();
                    if (! $avaliacao) {
                        return;
                    }

                    $valor = $state === '' ? null : str_replace(',', '.', $state);

                    if ($valor !== null && (! is_numeric($valor) || $valor < 0 || $valor > 10)) {
                        return;
                    }

                    Nota::updateOrCreate(
                        [
                            'avaliacao_id' => $avaliacao->id,
                            'matricula_id' => $matricula->id,
                        ],
                        [
                            'valor' => $valor,
                        ]
                    );
                });
        }

        return $table
            ->query(fn () => Disciplina::query()->whereIn('id', $disciplinasIds))
            ->heading($etapa->nome)
            ->columns([
                TextColumn::make('nome')
                    ->label('Disciplina')
                    ->weight('bold'),
                ...$dynamicColumns,
            ])
            ->paginated(false);
    }

    public function render()
    {
        return view('livewire.boletim-etapa-table'); // Reuse the same simple view
    }
}
