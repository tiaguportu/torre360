<?php

namespace App\Filament\Resources\CronogramaAulas\Pages;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use App\Models\Matricula;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class LancarFrequencia extends EditRecord
{
    protected static string $resource = CronogramaAulaResource::class;

    protected static ?string $title = 'Lançar Frequências';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('darPresencaTodos')
                ->label('Dar Presença para Todos')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    // Atualiza no banco
                    foreach ($this->record->frequencias as $frequencia) {
                        $frequencia->update(['situacao' => 'presente']);
                    }

                    // Recarrega os dados para a UI
                    $this->record->load('frequencias');
                    $this->fillForm();

                    Notification::make()
                        ->title('Todos os alunos marcados como presente!')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->load('frequencias');

        $matriculasDaTurma = Matricula::where('turma_id', $this->record->turma_id)->get();
        $frequenciasExistentes = $this->record->frequencias->pluck('matricula_id')->toArray();

        $newRecords = false;
        foreach ($matriculasDaTurma as $matricula) {
            if (! in_array($matricula->id, $frequenciasExistentes)) {
                $this->record->frequencias()->create([
                    'matricula_id' => $matricula->id,
                    'situacao' => null, // Deixa em branco conforme solicitado
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
                        Placeholder::make('data_info')
                            ->label('Data')
                            ->content(fn (?Model $record): string => $record?->data ? Carbon::parse($record->data)->format('d/m/Y') : '-'),
                        Placeholder::make('horario_info')
                            ->label('Horário')
                            ->content(fn (?Model $record): string => $record ? ($record->hora_inicio.' - '.$record->hora_fim) : '-'),
                    ])
                    ->columns(1),

                Section::make('Frequência dos Alunos')
                    ->description('Marque a presença dos alunos matriculados na turma para esta aula.')
                    ->schema([
                        Repeater::make('frequencias')
                            ->relationship('frequencias')
                            ->schema([
                                Select::make('matricula_id')
                                    ->label('Aluno')
                                    ->options(fn (): array => Matricula::where('turma_id', $this->record->turma_id)
                                        ->with('pessoa')
                                        ->get()
                                        ->mapWithKeys(fn ($m) => [$m->id => $m->pessoa?->nome ?? ("#{$m->id}")])
                                        ->toArray()
                                    )
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(['md' => 1]),
                                Select::make('situacao')
                                    ->label('Situação')
                                    ->placeholder('Selecione...')
                                    ->options([
                                        'presente' => 'Presente',
                                        'ausente' => 'Ausente',
                                    ])
                                    ->required()
                                    ->selectablePlaceholder(true)
                                    ->columnSpan(['md' => 1]),
                            ])
                            ->columns(['md' => 2])
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
        return 'Frequências salvas com sucesso!';
    }
}
