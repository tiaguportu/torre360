<?php

namespace App\Filament\Resources\AvaliacaoHabilidades\Pages;

use App\Enums\ConceitoHabilidade;
use App\Filament\Resources\AvaliacaoHabilidades\AvaliacaoHabilidadeResource;
use App\Models\NotaHabilidade;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
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
        $avaliacao = $this->getRecord();
        $turma = $avaliacao->turma;

        $components = [
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
        ];

        if ($turma) {
            $matriculas = $turma->matriculas()
                ->join('pessoa', 'matricula.pessoa_id', '=', 'pessoa.id')
                ->select('matricula.*', 'pessoa.nome as aluno_nome')
                ->orderBy('pessoa.nome')
                ->with(['pessoa'])
                ->get();

            $habilidades = $turma->habilidades;

            if ($habilidades->isEmpty()) {
                $components[] = Section::make('Aviso')
                    ->schema([
                        Placeholder::make('aviso_habilidades')
                            ->label('')
                            ->content('Atenção: A turma desta avaliação não possui habilidades vinculadas no cadastro.'),
                    ]);
            } elseif ($matriculas->isEmpty()) {
                $components[] = Section::make('Aviso')
                    ->schema([
                        Placeholder::make('aviso_matriculas')
                            ->label('')
                            ->content('Atenção: A turma desta avaliação não possui alunos matriculados.'),
                    ]);
            } else {
                foreach ($matriculas as $matricula) {
                    $habilidadeFields = [];
                    foreach ($habilidades as $habilidade) {
                        $habilidadeFields[] = Grid::make(12)
                            ->schema([
                                Placeholder::make("hab_nome_{$matricula->id}_{$habilidade->id}")
                                    ->label('')
                                    ->content($habilidade->nome.' ('.$habilidade->codigo.')')
                                    ->columnSpan(4),
                                Select::make("notas.{$matricula->id}.{$habilidade->id}.conceito")
                                    ->label('Conceito')
                                    ->options(ConceitoHabilidade::class)
                                    ->nullable()
                                    ->columnSpan(3),
                                Textarea::make("notas.{$matricula->id}.{$habilidade->id}.observacao")
                                    ->label('Observação Pedagógica')
                                    ->rows(1)
                                    ->columnSpan(5),
                            ]);
                    }

                    $components[] = Section::make($matricula->pessoa?->nome ?? 'Sem Nome')
                        ->schema($habilidadeFields)
                        ->collapsed();
                }
            }
        } else {
            $components[] = Section::make('Aviso')
                ->schema([
                    Placeholder::make('aviso_turma')
                        ->label('')
                        ->content('Atenção: Avaliação sem turma vinculada.'),
                ]);
        }

        return $schema
            ->columns(1)
            ->components($components);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['notas'] = $this->obterTodasNotasHabilidade();

        return $data;
    }

    protected function obterTodasNotasHabilidade(): array
    {
        $avaliacao = $this->getRecord();
        $turma = $avaliacao->turma;
        if (! $turma) {
            return [];
        }

        $notasExistentes = NotaHabilidade::where('avaliacao_habilidade_id', $avaliacao->id)
            ->get();

        $state = [];
        foreach ($notasExistentes as $nota) {
            $state[$nota->matricula_id][$nota->habilidade_id] = [
                'conceito' => $nota->conceito?->value ?? null,
                'observacao' => $nota->observacao ?? null,
            ];
        }

        return $state;
    }

    public function saveNotas(bool $shouldRedirect = true): void
    {
        $avaliacao = $this->getRecord();
        $notas = $this->data['notas'] ?? [];

        foreach ($notas as $matriculaId => $habilidades) {
            foreach ($habilidades as $habilidadeId => $dados) {
                $conceito = $dados['conceito'] ?? null;
                $observacao = $dados['observacao'] ?? null;

                if ($conceito !== '' && $conceito !== null) {
                    NotaHabilidade::updateOrCreate(
                        [
                            'avaliacao_habilidade_id' => $avaliacao->id,
                            'matricula_id' => $matriculaId,
                            'habilidade_id' => $habilidadeId,
                        ],
                        [
                            'conceito' => $conceito,
                            'observacao' => $observacao,
                        ]
                    );
                } else {
                    NotaHabilidade::where([
                        'avaliacao_habilidade_id' => $avaliacao->id,
                        'matricula_id' => $matriculaId,
                        'habilidade_id' => $habilidadeId,
                    ])->delete();
                }
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
        $html = '<p>Esta página é destinada ao lançamento em lote de notas/conceitos de habilidades para a avaliação selecionada.</p>';
        $html .= '<h3>Instruções e Funcionalidades:</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Lançamento por Aluno:</strong> Cada aluno matriculado na turma possui uma seção colapsável. Clique sobre o nome do aluno para expandir e visualizar a lista de habilidades a avaliar.</li>';
        $html .= '<li><strong>Conceitos e Observações:</strong> Selecione o conceito correspondente (ex: Realiza Bem, Em Desenvolvimento, etc.) para cada habilidade e adicione uma observação pedagógica se necessário.</li>';
        $html .= '<li><strong>Salvar Notas:</strong> Lançados os conceitos e observações, clique em "Salvar Notas" no topo da página para gravar as alterações no sistema.</li>';
        $html .= '</ul>';

        return $html;
    }
}
