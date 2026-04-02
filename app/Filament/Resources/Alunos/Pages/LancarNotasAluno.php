<?php

namespace App\Filament\Resources\Alunos\Pages;

use App\Filament\Resources\Alunos\AlunoResource;
use App\Models\Avaliacao;
use App\Models\Nota;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LancarNotasAluno extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = AlunoResource::class;

    protected string $view = 'filament.alunos.lancar-notas-aluno';

    protected static ?string $title = 'Lançar Notas do Aluno';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $matricula = $this->record->matriculas()->first();
        if (! $matricula || ! $matricula->turma_id) {
            return;
        }

        $turma = $matricula->turma;

        $avaliacoes = Avaliacao::where('turma_id', $turma->id)
            ->with(['disciplina', 'etapaAvaliativa', 'categoria'])
            ->get()
            ->groupBy('disciplina_id');

        $notasExistentes = Nota::where('matricula_id', $matricula->id)
            ->pluck('valor', 'avaliacao_id')
            ->toArray();

        $fillState = [];
        foreach ($avaliacoes as $disciplinaId => $avaliacoesDisciplina) {
            $items = [];
            foreach ($avaliacoesDisciplina as $avaliacao) {
                $items[] = [
                    'avaliacao_id' => $avaliacao->id,
                    'avaliacao_nome' => "{$avaliacao->etapaAvaliativa?->nome} - {$avaliacao->categoria?->nome} (".($avaliacao->data_prevista ? date('d/m/Y', strtotime($avaliacao->data_prevista)) : 'S/D').')',
                    'nota_maxima' => $avaliacao->nota_maxima ?? 10,
                    'valor' => $notasExistentes[$avaliacao->id] ?? null,
                ];
            }
            $fillState["notas_disciplina_{$disciplinaId}"] = $items;
        }

        $this->form->fill($fillState);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar Notas')
                ->color('primary')
                ->action('saveNotas'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $matricula = $this->record->matriculas()->first();

        if (! $matricula || ! $matricula->turma_id) {
            return $schema->components([
                Section::make('Aviso')
                    ->description('Este aluno não possui uma turma vinculada à sua matrícula ativa.')
                    ->schema([
                        Placeholder::make('warning')
                            ->label('')
                            ->content('Favor vincular o aluno a uma turma antes de lançar as notas.'),
                    ]),
            ]);
        }

        $turma = $matricula->turma;
        $avaliacoesByDisciplina = Avaliacao::where('turma_id', $turma->id)
            ->with(['disciplina', 'etapaAvaliativa', 'categoria'])
            ->get()
            ->groupBy('disciplina_id');

        $sections = [];

        foreach ($avaliacoesByDisciplina as $disciplinaId => $avaliacoes) {
            $disciplinaNome = $avaliacoes->first()->disciplina?->nome ?? 'Sem Disciplina';

            $sections[] = Section::make($disciplinaNome)
                ->schema([
                    Repeater::make("notas_disciplina_{$disciplinaId}")
                        ->label('')
                        ->schema([
                            Hidden::make('avaliacao_id'),
                            TextInput::make('avaliacao_nome')
                                ->label('Avaliação')
                                ->readOnly()
                                ->extraAttributes([
                                    'class' => 'bg-gray-100 dark:bg-gray-800',
                                ])
                                ->columnSpan(3),
                            TextInput::make('valor')
                                ->label('Nota')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(fn ($get) => $get('nota_maxima') ?? 10)
                                ->columnSpan(1)
                                ->extraInputAttributes(['wire:keydown.enter' => 'saveNotas']),
                            Hidden::make('nota_maxima'),
                        ])
                        ->columns(4)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                ])
                ->collapsible();
        }

        return $schema->components($sections);
    }

    public function saveNotas(): void
    {
        $data = $this->form->getState();
        $matricula = $this->record->matriculas()->first();

        if (! $matricula) {
            return;
        }

        $count = 0;
        foreach ($data as $key => $values) {
            if (str_starts_with($key, 'notas_disciplina_') && is_array($values)) {
                foreach ($values as $item) {
                    if (isset($item['avaliacao_id']) && (isset($item['valor']) && $item['valor'] !== '' && $item['valor'] !== null)) {
                        $valor = (float) str_replace(',', '.', $item['valor']);

                        Nota::updateOrCreate(
                            [
                                'avaliacao_id' => $item['avaliacao_id'],
                                'matricula_id' => $matricula->id,
                            ],
                            [
                                'valor' => $valor,
                            ]
                        );
                        $count++;
                    }
                }
            }
        }

        Notification::make()
            ->title($count > 0 ? 'Notas salvas com sucesso!' : 'Nenhuma nota foi alterada.')
            ->success()
            ->send();

        $this->redirect(AlunoResource::getUrl('index'));
    }

    public function getBreadcrumbs(): array
    {
        return [
            AlunoResource::getUrl() => 'Alunos',
            '#' => 'Lançar Notas',
        ];
    }
}
