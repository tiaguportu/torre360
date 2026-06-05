<?php

namespace App\Filament\Resources\AvaliacaoHabilidades\Pages;

use App\Enums\ConceitoHabilidade;
use App\Filament\Resources\AvaliacaoHabilidades\AvaliacaoHabilidadeResource;
use App\Models\NotaHabilidade;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class LancarNotaHabilidade extends EditRecord
{
    protected static string $resource = AvaliacaoHabilidadeResource::class;

    protected static ?string $title = 'Lançar Notas de Habilidade';

    public function authorizeAccess(): void
    {
        $this->authorize('update', $this->getRecord());
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
                ->modalHeading('Ajuda: Lançamento de Notas de Habilidade')
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
                        Placeholder::make('turma_info')
                            ->label('Turma')
                            ->content(fn (?Model $record): string => $record?->turma?->nome ?? '-'),
                        Placeholder::make('etapa_info')
                            ->label('Etapa Avaliativa')
                            ->content(fn (?Model $record): string => $record?->etapaAvaliativa?->nome ?? '-'),
                        Placeholder::make('professor_info')
                            ->label('Professor')
                            ->content(fn (?Model $record): string => $record?->professor?->nome ?? '-'),
                    ])->columns(3),

                Section::make('Selecione a Habilidade a Avaliar')
                    ->schema([
                        Select::make('habilidade_id')
                            ->label('Habilidade')
                            ->options(fn () => $this->getRecord()->turma?->habilidades?->pluck('nome', 'id') ?? [])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->carregarNotasDaHabilidade($state))
                            ->helperText(fn () => $this->getRecord()->turma?->habilidades()->count() === 0
                                ? 'Atenção: A turma desta avaliação não possui habilidades vinculadas no cadastro.'
                                : null),
                    ]),

                Section::make('Notas e Conceitos dos Alunos')
                    ->schema([
                        Repeater::make('notas_alunos')
                            ->label('')
                            ->schema([
                                TextInput::make('aluno_nome')
                                    ->label('Aluno')
                                    ->disabled()
                                    ->columnSpan(2),
                                Hidden::make('matricula_id'),
                                Select::make('conceito')
                                    ->label('Conceito')
                                    ->options(ConceitoHabilidade::class)
                                    ->required()
                                    ->columnSpan(1),
                                Textarea::make('observacao')
                                    ->label('Observação Pedagógica')
                                    ->rows(1)
                                    ->columnSpan(3),
                            ])
                            ->columns(6)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $avaliacao = $this->getRecord();
        $primeiraHabilidade = $avaliacao->turma?->habilidades()->first()?->id;

        if ($primeiraHabilidade) {
            $data['habilidade_id'] = $primeiraHabilidade;
            $data['notas_alunos'] = $this->obterNotasDaHabilidade($primeiraHabilidade);
        } else {
            $data['notas_alunos'] = [];
        }

        return $data;
    }

    public function carregarNotasDaHabilidade(?int $habilidadeId): void
    {
        if (! $habilidadeId) {
            $this->data['notas_alunos'] = [];

            return;
        }

        $this->data['notas_alunos'] = $this->obterNotasDaHabilidade($habilidadeId);
    }

    protected function obterNotasDaHabilidade(int $habilidadeId): array
    {
        $avaliacao = $this->getRecord();
        $turma = $avaliacao->turma;
        if (! $turma) {
            return [];
        }

        $matriculas = $turma->matriculas()
            ->join('pessoa', 'matricula.pessoa_id', '=', 'pessoa.id')
            ->select('matricula.*', 'pessoa.nome as aluno_nome')
            ->orderBy('pessoa.nome')
            ->with(['pessoa'])
            ->get();

        $notasExistentes = NotaHabilidade::where([
            'avaliacao_habilidade_id' => $avaliacao->id,
            'habilidade_id' => $habilidadeId,
        ])->get()->keyBy('matricula_id');

        $state = [];
        foreach ($matriculas as $matricula) {
            $nota = $notasExistentes->get($matricula->id);
            $state[] = [
                'matricula_id' => $matricula->id,
                'aluno_nome' => $matricula->pessoa?->nome ?? 'Sem Nome',
                'conceito' => $nota?->conceito?->value ?? null,
                'observacao' => $nota?->observacao ?? null,
            ];
        }

        return $state;
    }

    public function saveNotas(bool $shouldRedirect = true): void
    {
        $avaliacao = $this->getRecord();
        $habilidadeId = $this->data['habilidade_id'] ?? null;

        if (! $habilidadeId) {
            Notification::make()
                ->title('Selecione uma Habilidade')
                ->danger()
                ->send();

            return;
        }

        $notasAlunos = $this->data['notas_alunos'] ?? [];

        foreach ($notasAlunos as $item) {
            if (! isset($item['matricula_id'])) {
                continue;
            }

            if (isset($item['conceito']) && $item['conceito'] !== '' && $item['conceito'] !== null) {
                NotaHabilidade::updateOrCreate(
                    [
                        'avaliacao_habilidade_id' => $avaliacao->id,
                        'matricula_id' => $item['matricula_id'],
                        'habilidade_id' => $habilidadeId,
                    ],
                    [
                        'conceito' => $item['conceito'],
                        'observacao' => $item['observacao'] ?? null,
                    ]
                );
            } else {
                NotaHabilidade::where([
                    'avaliacao_habilidade_id' => $avaliacao->id,
                    'matricula_id' => $item['matricula_id'],
                    'habilidade_id' => $habilidadeId,
                ])->delete();
            }
        }

        Notification::make()
            ->title('Notas de Habilidade salvas com sucesso!')
            ->success()
            ->send();

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
        $html = '<p>Esta página é destinada ao lançamento de notas de habilidades em lote para a avaliação de habilidades selecionada.</p>';
        $html .= '<h3>Instruções e Funcionalidades:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Habilidade:</strong> Selecione a Habilidade desejada no topo. O formulário recarregará os conceitos dos alunos correspondentes a ela de forma dinâmica.</li>';
        $html .= '<li><strong>Salvar Notas:</strong> Lançados os conceitos e observações, clique em "Salvar Notas" para registrar no banco de dados.</li>';
        $html .= '</ul>';

        return $html;
    }
}
