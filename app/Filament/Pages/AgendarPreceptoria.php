<?php

namespace App\Filament\Pages;

use App\Models\CronogramaAula;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class AgendarPreceptoria extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static string|\UnitEnum|null $navigationGroup = 'Preceptoria';

    protected static ?string $navigationLabel = 'Agendar Preceptoria';

    protected static ?string $title = 'Agendar Preceptoria';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.agendar-preceptoria';

    public ?array $data = [];

    /**
     * Apenas pessoas vinculadas (aluno ou responsável) ou admins podem ver esta página.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin', 'secretaria'])) {
            return true;
        }

        return (bool) $user->pessoa;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * Retorna o objeto Pessoa do usuário autenticado.
     */
    protected function getPessoaDoUsuario(): ?Pessoa
    {
        return auth()->user()?->pessoa;
    }

    /**
     * Retorna todas as matrículas acessíveis:
     * - Se for aluno: suas próprias matrículas.
     * - Se for responsável: matrículas dos seus alunos vinculados.
     * - Se for admin/secretaria: todas.
     */
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

        // Matrículas da própria pessoa (caso seja aluno)
        $condicoes = Matricula::query()->where('pessoa_id', $pessoa->id);

        // Matrículas dos alunos vinculados como responsável
        $alunosIds = $pessoa->alunos()->pluck('pessoa.id');
        if ($alunosIds->isNotEmpty()) {
            $condicoes->orWhereIn('pessoa_id', $alunosIds);
        }

        return $condicoes->with(['pessoa', 'turma', 'periodoLetivo'])->get();
    }

    /**
     * Dado uma matrícula, lista os professores vinculados:
     * - Via cronograma_aula da turma
     * - Via professor_conselheiro da turma
     */
    protected function getProfessoresDaMatricula(int $matriculaId): Collection
    {
        $matricula = Matricula::with(['turma.professorConselheiro', 'turma.cronogramasAula.professor'])->find($matriculaId);

        if (! $matricula || ! $matricula->turma) {
            return collect();
        }

        $professores = collect();

        // 1. Professor Conselheiro da Turma
        if ($matricula->turma->professorConselheiro) {
            $professores->push($matricula->turma->professorConselheiro);
        }

        // 2. Professores via cronograma_aula
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
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('professor_id', null)),
                    ])
                    ->columnSpanFull(),

                Section::make('Selecionar Professor(a)')
                    ->schema([
                        Select::make('professor_id')
                            ->label('Professor(a)')
                            ->options(function (Get $get) use ($isAdminOrSecretaria) {
                                $matriculaId = $get('matricula_id');

                                if ($isAdminOrSecretaria && ! $matriculaId) {
                                    // Admins podem ver todos
                                    return Pessoa::orderBy('nome')->pluck('nome', 'id');
                                }

                                if (! $matriculaId) {
                                    return [];
                                }

                                return $this->getProfessoresDaMatricula((int) $matriculaId)
                                    ->pluck('nome', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get) => ! $get('matricula_id')),
                    ])
                    ->columnSpanFull(),

                Section::make('Data e Horário')
                    ->schema([
                        DatePicker::make('data')
                            ->label('Data')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(now()->toDateString()),

                        TimePicker::make('hora_inicio')
                            ->label('Hora Início')
                            ->required()
                            ->seconds(false),

                        TimePicker::make('hora_fim')
                            ->label('Hora Fim (Opcional)')
                            ->seconds(false)
                            ->nullable(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function agendar(): void
    {
        $raw = $this->form->getState();

        try {
            Preceptoria::create([
                'data' => $raw['data'],
                'hora_inicio' => $raw['hora_inicio'],
                'hora_fim' => $raw['hora_fim'] ?? null,
                'professor_id' => $raw['professor_id'],
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
