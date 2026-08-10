<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use App\Models\Matricula;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class LancarFrequencia extends EditRecord
{
    protected static string $resource = CronogramaAulaResource::class;

    protected static ?string $title = 'Lançamento Rápido no Diário';

    public function authorizeAccess(): void
    {
        $this->authorize('lancarFrequencia', $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData(['content' => $this->getHelpContent()]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),

            Action::make('darPresencaTodos')
                ->label('Dar Presença para Todos')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    foreach ($this->record->frequencias as $frequencia) {
                        $frequencia->update(['situacao' => 'presente']);
                    }

                    $this->record->load('frequencias');
                    $this->fillForm();

                    Notification::make()
                        ->title('Todos os alunos marcados como presente!')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        if (! $user || ! $user->can('LancarFrequencia:CronogramaAula')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-3 text-sm">
                <h3 class="font-bold text-base">Lançamento Rápido no Diário de Classe</h3>
                <p>Nesta página você pode realizar o registro completo da aula de forma ágil:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <td><b>Conteúdo Ministrado:</b> Descreva o que foi lecionado na aula.</td>
                    <td><b>Habilidades BNCC:</b> Associe as competências e habilidades desenvolvidas.</td>
                    <td><b>Dever de Casa:</b> Registre as tarefas passadas para a turma.</td>
                    <td><b>Anexos:</b> Faça upload de apresentações, PDFs ou materiais de suporte.</td>
                    <td><b>Chamada:</b> Marque Presença ou Ausência para cada estudante.</td>
                </ul>
            </div>
        ';
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->load(['frequencias', 'habilidades']);

        $matriculasDaTurma = Matricula::where('turma_id', $this->record->turma_id)->get();
        $frequenciasExistentes = $this->record->frequencias->pluck('matricula_id')->toArray();

        $newRecords = false;
        foreach ($matriculasDaTurma as $matricula) {
            if (! in_array($matricula->id, $frequenciasExistentes)) {
                $this->record->frequencias()->create([
                    'matricula_id' => $matricula->id,
                    'situacao' => null,
                ]);
                $newRecords = true;
            }
        }

        if ($newRecords) {
            $this->record->load('frequencias');
            $this->fillForm();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Aula')
                    ->schema([
                        Placeholder::make('turma_info')
                            ->label('Turma')
                            ->content(fn (?Model $record): string => $record?->turma?->nome ?? '-'),
                        Placeholder::make('disciplina_info')
                            ->label('Disciplina')
                            ->content(fn (?Model $record): string => $record?->disciplina?->nome ?? '-'),
                        Placeholder::make('professor_info')
                            ->label('Professor')
                            ->content(fn (?Model $record): string => $record?->professor?->nome ?? '-'),
                        Placeholder::make('data_info')
                            ->label('Data')
                            ->content(fn (?Model $record): string => $record?->data ? Carbon::parse($record->data)->format('d/m/Y') : '-'),
                        Placeholder::make('horario_info')
                            ->label('Horário')
                            ->content(fn (?Model $record): string => $record ? ($record->hora_inicio.' - '.$record->hora_fim) : '-'),
                        Placeholder::make('periodo_info')
                            ->label('Período Letivo')
                            ->content(fn (?Model $record): string => $record?->periodoLetivo?->nome ?? '-'),
                    ])
                    ->columns(['md' => 3, 'default' => 1]),

                Section::make('Conteúdo Pedagógico, BNCC & Materiais')
                    ->description('Registre os tópicos abordados na aula, deveres de casa e materiais anexos.')
                    ->schema([
                        Textarea::make('conteudo_ministrado')
                            ->label('Conteúdo Ministrado')
                            ->rows(3)
                            ->columnSpanFull(),

                        Select::make('habilidades')
                            ->label('Habilidades da BNCC Trabalhadas')
                            ->relationship('habilidades', 'nome')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn ($record) => ($record->codigo ? "[{$record->codigo}] " : '').$record->nome)
                            ->columnSpanFull(),

                        Textarea::make('dever_casa')
                            ->label('Dever / Tarefa de Casa')
                            ->rows(2)
                            ->placeholder('Descreva as tarefas e deveres passados para os alunos...')
                            ->columnSpanFull(),

                        FileUpload::make('anexo_material')
                            ->label('Anexos e Materiais de Aula')
                            ->multiple()
                            ->directory('materiais-aula')
                            ->preserveFilenames()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('Frequência dos Alunos (Chamada)')
                    ->description('Marque a presença dos alunos matriculados na turma para esta aula.')
                    ->schema([
                        Repeater::make('frequencias')
                            ->relationship('frequencias')
                            ->schema([
                                Select::make('matricula_id')
                                    ->label('Aluno')
                                    ->options(fn (): array => Matricula::where('turma_id', $this->record->turma_id)
                                        ->with(['pessoa', 'periodoLetivo', 'turma'])
                                        ->get()
                                        ->mapWithKeys(fn ($m) => [$m->id => "{$m->pessoa?->nome}"])
                                        ->toArray()
                                    )
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(['md' => 1]),
                                ToggleButtons::make('situacao')
                                    ->label('Situação')
                                    ->options([
                                        'presente' => 'Presente',
                                        'ausente' => 'Ausente',
                                    ])
                                    ->colors([
                                        'presente' => 'success',
                                        'ausente' => 'danger',
                                    ])
                                    ->icons([
                                        'presente' => 'heroicon-o-check',
                                        'ausente' => 'heroicon-o-x-circle',
                                    ])
                                    ->required()
                                    ->inline()
                                    ->live()
                                    ->columnSpan(['md' => 1]),
                            ])
                            ->columns(['md' => 2, 'default' => 1])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ])
                    ->columns(1),
            ])
            ->columns(1);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Lançamento no diário e frequências salvos com sucesso!';
    }
}
