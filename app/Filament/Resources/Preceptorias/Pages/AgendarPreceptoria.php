<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\CronogramaAula;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class AgendarPreceptoria extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = PreceptoriaResource::class;

    protected string $view = 'filament.pages.agendar-preceptoria';

    protected static ?string $title = 'Agendar Preceptoria';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getPessoaDoUsuario(): ?Pessoa
    {
        return auth()->user()?->pessoa;
    }

    protected function getMatriculasAcessiveis(): Collection
    {
        $user = auth()->user();

        if ($user->hasRole(['super_admin', 'admin', 'secretaria'])) {
            return Matricula::with(['pessoa', 'turma', 'periodoLetivo'])->get();
        }

        $pessoa = $this->getPessoaDoUsuario();
        if (! $pessoa) {
            return collect();
        }

        $condicoes = Matricula::query()->where('pessoa_id', $pessoa->id);

        $alunosIds = $pessoa->alunos()->pluck('pessoa.id');
        if ($alunosIds->isNotEmpty()) {
            $condicoes->orWhereIn('pessoa_id', $alunosIds);
        }

        return $condicoes->with(['pessoa', 'turma', 'periodoLetivo'])->get();
    }

    protected function getProfessoresDaMatricula(int $matriculaId): Collection
    {
        $matricula = Matricula::with(['turma.professorConselheiro', 'turma.cronogramasAula.professor'])->find($matriculaId);

        if (! $matricula || ! $matricula->turma) {
            return collect();
        }

        $professores = collect();

        if ($matricula->turma->professorConselheiro) {
            $professores->push($matricula->turma->professorConselheiro);
        }

        CronogramaAula::where('turma_id', $matricula->turma_id)
            ->whereNotNull('pessoa_id')
            ->with('professor')
            ->get()
            ->each(fn ($ca) => $ca->professor && $professores->push($ca->professor));

        return $professores->unique('id');
    }

    public function form(Schema $schema): Schema
    {
        $user = auth()->user();
        $isAdminOrSecretaria = $user?->hasRole(['super_admin', 'admin', 'secretaria']);

        return $schema
            ->components([
                Section::make('Selecionar Matrícula do Aluno')
                    ->schema([
                        Select::make('matricula_id')
                            ->label('Matrícula / Aluno')
                            ->options(function () {
                                return $this->getMatriculasAcessiveis()
                                    ->mapWithKeys(fn (Matricula $m) => [$m->id => $m->label_exibicao]);
                            })
                            ->searchable()
                            ->required()
                            ->live(),
                    ])
                    ->columnSpanFull(),

                Section::make('Horários Disponíveis')
                    ->schema([
                        Select::make('preceptoria_id')
                            ->label('Horário Disponível')
                            ->options(function (Get $get) use ($isAdminOrSecretaria) {
                                $matriculaId = $get('matricula_id');

                                if (! $matriculaId) {
                                    return [];
                                }

                                $query = Preceptoria::query()
                                    ->whereNull('matricula_id')
                                    ->where('data', '>=', now()->toDateString())
                                    ->with('professor');

                                if (! $isAdminOrSecretaria) {
                                    $professoresIds = $this->getProfessoresDaMatricula((int) $matriculaId)->pluck('id');
                                    $query->whereIn('professor_id', $professoresIds);
                                }

                                return $query->orderBy('data')
                                    ->orderBy('hora_inicio')
                                    ->get()
                                    ->mapWithKeys(function (Preceptoria $p) {
                                        $data = $p->data ? Carbon::parse($p->data)->format('d/m/Y') : '';
                                        $inicio = $p->hora_inicio ? Carbon::parse($p->hora_inicio)->format('H:i') : '';
                                        $fim = $p->hora_fim ? ' - '.Carbon::parse($p->hora_fim)->format('H:i') : '';

                                        return [
                                            $p->id => "{$p->professor?->nome} - {$data} às {$inicio}{$fim}",
                                        ];
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get) => ! $get('matricula_id')),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function agendar(): void
    {
        $raw = $this->form->getState();

        try {
            $preceptoria = Preceptoria::findOrFail($raw['preceptoria_id']);

            $preceptoria->update([
                'matricula_id' => $raw['matricula_id'],
            ]);

            Notification::make()
                ->title('Preceptoria agendada com sucesso!')
                ->success()
                ->send();

            $this->form->fill();

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao agendar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
