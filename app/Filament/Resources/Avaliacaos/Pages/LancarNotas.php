<?php

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Filament\Resources\Avaliacaos\Schemas\AvaliacaoForm;
use App\Models\Nota;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

class LancarNotas extends EditRecord
{
    protected static string $resource = AvaliacaoResource::class;

    protected static ?string $title = 'Lançar Notas';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_notes')
                ->label('Salvar Notas')
                ->action('saveNotas')
                ->color('primary'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informações da Avaliação')
                    ->schema(
                        array_map(
                            fn ($component) => $component->disabled(),
                            AvaliacaoForm::getSchemaComponents()
                        )
                    )->columns(3),

                Section::make('Notas dos Alunos')
                    ->schema([
                        Repeater::make('notas_alunos')
                            ->label('')
                            ->schema([
                                TextInput::make('aluno_nome')
                                    ->label('Aluno')
                                    ->disabled()
                                    ->columnSpan(3),
                                Hidden::make('matricula_id'),
                                TextInput::make('valor')
                                    ->label('Nota')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(fn ($record) => $record->nota_maxima ?? 10)
                                    ->columnSpan(1)
                                    ->live()
                                    ->extraInputAttributes(['wire:keydown.enter' => 'saveNotas']),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $avaliacao = $this->getRecord();
        $turma = $avaliacao->turma;

        if (! $turma) {
            return $data;
        }

        $matriculas = $turma->matriculas()
            ->join('pessoa', 'matricula.pessoa_id', '=', 'pessoa.id')
            ->select('matricula.*', 'pessoa.nome as aluno_nome')
            ->orderBy('pessoa.nome')
            ->with(['pessoa'])
            ->get();

        $notasExistentes = $avaliacao->notas()->pluck('valor', 'matricula_id')->toArray();

        $state = [];
        foreach ($matriculas as $matricula) {
            $state[] = [
                'matricula_id' => $matricula->id,
                'aluno_nome' => $matricula->pessoa?->nome ?? 'Sem Nome',
                'valor' => $notasExistentes[$matricula->id] ?? null,
            ];
        }

        $data['notas_alunos'] = $state;

        return $data;
    }

    public function saveNotas(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $avaliacao = $this->getRecord();
        $notasAlunos = $this->data['notas_alunos'] ?? [];

        Log::info('Dados para salvar (saveNotas):', $this->data);

        foreach ($notasAlunos as $index => $item) {
            if (! isset($item['matricula_id'])) {
                continue;
            }

            if (isset($item['valor']) && $item['valor'] !== '' && $item['valor'] !== null) {
                $valor = (float) str_replace(',', '.', $item['valor']);

                Nota::updateOrCreate(
                    [
                        'avaliacao_id' => $avaliacao->id,
                        'matricula_id' => $item['matricula_id'],
                    ],
                    [
                        'valor' => $valor,
                    ]
                );
            }
        }

        if ($shouldSendSavedNotification) {
            Notification::make()
                ->title('Notas salvas com sucesso!')
                ->success()
                ->send();
        }

        if ($shouldRedirect) {
            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
