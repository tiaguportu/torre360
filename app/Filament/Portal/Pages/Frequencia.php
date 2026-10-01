<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Portal\Concerns\InteractsWithMatriculaSelecionada;
use App\Models\CronogramaAula;
use App\Models\FrequenciaEscolar;
use App\Services\FrequenciaAlunoService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Presenças e faltas do aluno, com resumo por disciplina e alerta de frequência mínima.
 */
class Frequencia extends Page implements HasTable
{
    use HasAjudaAction;
    use InteractsWithMatriculaSelecionada;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Frequência';

    protected static ?string $slug = 'frequencia';

    protected string $view = 'filament.portal.pages.frequencia';

    public function mount(): void
    {
        $this->matriculaId = $this->getMatriculaSelecionada()?->id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResumo(): ?array
    {
        $matricula = $this->getMatriculaSelecionada();

        return $matricula ? app(FrequenciaAlunoService::class)->resumo($matricula) : null;
    }

    public function table(Table $table): Table
    {
        $aulas = (new CronogramaAula)->getTable();

        return $table
            ->query($this->getFrequenciasQuery())
            ->columns([
                TextColumn::make('aula_data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('cronogramaAula.disciplina.nome')
                    ->label('Disciplina'),
                TextColumn::make('aula_hora_inicio')
                    ->label('Horário')
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 5) : '—'),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'ausente' ? 'Falta' : 'Presente')
                    ->color(fn (?string $state): string => $state === 'ausente' ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('situacao')
                    ->label('Situação')
                    ->options(['ausente' => 'Faltas', 'presente' => 'Presenças'])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, string $valor): Builder => $q->where('frequencia_escolar.situacao', $valor),
                    )),
                SelectFilter::make('disciplina')
                    ->label('Disciplina')
                    ->options(fn (): array => collect($this->getResumo()['por_disciplina'] ?? [])->pluck('nome', 'disciplina_id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, string|int $valor): Builder => $q->where("{$aulas}.disciplina_id", $valor),
                    )),
            ])
            ->defaultSort('aula_data', 'desc')
            ->stackedOnMobile();
    }

    protected function getFrequenciasQuery(): Builder
    {
        $frequencias = (new FrequenciaEscolar)->getTable();
        $aulas = (new CronogramaAula)->getTable();

        return FrequenciaEscolar::query()
            ->join($aulas, "{$aulas}.id", '=', "{$frequencias}.cronograma_aula_id")
            ->where("{$frequencias}.matricula_id", $this->getMatriculaSelecionada()?->id ?? 0)
            ->whereIn("{$frequencias}.situacao", ['presente', 'ausente'])
            ->select("{$frequencias}.*")
            ->selectRaw("{$aulas}.data as aula_data")
            ->selectRaw("{$aulas}.hora_inicio as aula_hora_inicio")
            ->with('cronogramaAula.disciplina');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Frequência', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('✅', 'Frequência', 'Presenças e faltas do aluno.')
            ->secao('🎯 O que você pode fazer?', [
                ['📊', 'Resumo', 'Veja o panorama geral de presenças e faltas.'],
                ['📋', 'Lista de aulas', 'Consulte data, disciplina, horário e situação de cada aula.'],
                ['🔎', 'Filtros', 'Filtre por situação ou por disciplina.'],
            ])
            ->dica('Em caso de dúvida sobre uma falta, abra um chamado na Central de Atendimento.');
    }
}
