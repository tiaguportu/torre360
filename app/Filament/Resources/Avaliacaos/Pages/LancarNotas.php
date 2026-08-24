<?php

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Services\NotaLancamentoService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LancarNotas extends EditRecord
{
    protected static string $resource = AvaliacaoResource::class;

    protected static ?string $title = 'Lançar Notas';

    public function authorizeAccess(): void
    {
        $this->authorize('lancarNotas', $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_notes')
                ->label('Salvar Notas')
                ->action('saveNotas')
                ->color('primary'),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Lançamento de Notas')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informações da Avaliação')
                    ->schema([
                        Placeholder::make('etapa_avaliativa_info')
                            ->label('Etapa Avaliativa')
                            ->content(fn (?Model $record): string => $record?->etapaAvaliativa?->nome ?? '-'),
                        Placeholder::make('turma_info')
                            ->label('Turma')
                            ->content(fn (?Model $record): string => $record?->turma?->nome ?? '-'),
                        Placeholder::make('disciplina_info')
                            ->label('Disciplina')
                            ->content(fn (?Model $record): string => $record?->disciplina?->nome ?? '-'),
                        Placeholder::make('professor_info')
                            ->label('Professor')
                            ->content(fn (?Model $record): string => $record?->professor?->nome ?? '-'),
                        Placeholder::make('categoria_info')
                            ->label('Categoria')
                            ->content(fn (?Model $record): string => $record?->categoria?->nome ?? '-'),
                        Placeholder::make('data_prevista_info')
                            ->label('Data Prevista')
                            ->content(fn (?Model $record): string => $record?->data_prevista ? Carbon::parse($record->data_prevista)->format('d/m/Y') : '-'),
                        Placeholder::make('nota_maxima_info')
                            ->label('Nota Máxima')
                            ->content(fn (?Model $record): string => $record?->nota_maxima ?? '-'),
                    ])->columns(3),

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
                                    ->maxValue(fn () => $this->getRecord()->nota_maxima ?? 10)
                                    ->validationMessages([
                                        'max' => 'A nota não pode ser maior que a nota máxima da avaliação (:max).',
                                    ])
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
        $data['notas_alunos'] = app(NotaLancamentoService::class)->estadoNotasParaGrade($this->getRecord());

        return $data;
    }

    public function saveNotas(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $avaliacao = $this->getRecord();
        $notasAlunos = $this->data['notas_alunos'] ?? [];

        try {
            app(NotaLancamentoService::class)->salvarNotas($avaliacao, $notasAlunos);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Erro ao salvar nota')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
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

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $canLancar = $user->can('LancarNotas:Avaliacao');

        $html = '<p>Esta página é destinada ao lançamento de notas para os alunos matriculados na turma desta avaliação.</p>';
        $html .= '<h3>Instruções e Funcionalidades:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Planilha de Notas:</strong> Insira a nota de cada aluno no campo correspondente. O valor deve respeitar o limite máximo estabelecido para a avaliação.</li>';

        if ($canLancar) {
            $html .= '<li><strong>Salvar Notas:</strong> Clique no botão "Salvar Notas" no topo para consolidar as notas no sistema.</li>';
        }

        $html .= '<li><strong>Validação:</strong> Notas maiores que a pontuação máxima definida para esta avaliação não serão permitidas.</li>';
        $html .= '</ul>';

        return $html;
    }
}
