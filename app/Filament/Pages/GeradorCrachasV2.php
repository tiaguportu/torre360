<?php

namespace App\Filament\Pages;

use App\Enums\SituacaoMatricula;
use App\Models\Pessoa;
use App\Models\TemplateCrachaV2;
use App\Models\Turma;
use App\Services\TemplateCrachaV2Service;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeradorCrachasV2 extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Gerador de Crachás V2';

    protected static ?string $title = 'Gerador de Crachás V2';

    protected static ?string $slug = 'secretaria/gerador-crachas-v2';

    protected string $view = 'filament.pages.gerador-crachas-v2';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'tipo_selecao' => 'individual',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Parâmetros de Geração (Versão 2 - SVG Edit)')
                    ->description('Selecione o modelo de crachá V2 e defina as pessoas para quem deseja gerar.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('template_id')
                                    ->label('Modelo de Crachá V2')
                                    ->options(TemplateCrachaV2::pluck('nome', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                Select::make('tipo_selecao')
                                    ->label('Selecionar Pessoas por')
                                    ->options([
                                        'turma' => 'Por Turma (Todos os Alunos Ativos)',
                                        'individual' => 'Seleção Individual (Livre)',
                                    ])
                                    ->default('individual')
                                    ->required()
                                    ->live(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('turma_ids')
                                    ->label('Turmas')
                                    ->multiple()
                                    ->options(Turma::pluck('nome', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(fn (Get $get) => $get('tipo_selecao') === 'turma')
                                    ->visible(fn (Get $get) => $get('tipo_selecao') === 'turma'),

                                Select::make('pessoa_ids')
                                    ->label('Selecione as Pessoas')
                                    ->multiple()
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => Pessoa::where('nome', 'like', "%{$search}%")
                                        ->orWhere('cpf', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->pluck('nome', 'id')
                                        ->toArray())
                                    ->getOptionLabelUsing(fn ($value): ?string => Pessoa::find($value)?->nome)
                                    ->required(fn (Get $get) => $get('tipo_selecao') === 'individual')
                                    ->visible(fn (Get $get) => $get('tipo_selecao') === 'individual'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gerador de Crachás V2')
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

    private function getHelpContent(): string
    {
        $html = '<p>Esta página permite realizar a geração de crachás em PDF utilizando os templates da **Versão 2 (SVG Edit)**.</p>';
        $html .= '<h3>Como usar?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Modelo de Crachá V2:</strong> Selecione o layout V2 cadastrado no sistema.</li>';
        $html .= '<li><strong>Origem:</strong> Defina se deseja gerar para todos os alunos de uma ou mais **Turmas** ou de forma **Individual** selecionando os nomes das pessoas.</li>';
        $html .= '<li><strong>Gerar:</strong> Confirme no botão abaixo para baixar o PDF gerado.</li>';
        $html .= '</ul>';

        return $html;
    }

    public function gerar(): ?StreamedResponse
    {
        $state = $this->form->getState();

        $templateId = $state['template_id'] ?? null;
        $tipoSelecao = $state['tipo_selecao'] ?? 'individual';
        $turmaIds = $state['turma_ids'] ?? [];
        $pessoaIds = $state['pessoa_ids'] ?? [];

        if (! $templateId) {
            Notification::make()
                ->title('Erro de Validação')
                ->body('Selecione o modelo do crachá.')
                ->danger()
                ->send();

            return null;
        }

        $template = TemplateCrachaV2::find($templateId);

        if (! $template) {
            Notification::make()
                ->title('Erro')
                ->body('Modelo de crachá não encontrado.')
                ->danger()
                ->send();

            return null;
        }

        $pessoasComTurma = collect();

        if ($tipoSelecao === 'turma') {
            if (empty($turmaIds)) {
                Notification::make()
                    ->title('Erro de Validação')
                    ->body('Selecione pelo menos uma turma.')
                    ->danger()
                    ->send();

                return null;
            }

            $turmas = Turma::whereIn('id', $turmaIds)->get();

            foreach ($turmas as $turma) {
                $matriculas = $turma->matriculas()
                    ->where('situacao', SituacaoMatricula::ATIVA)
                    ->with('pessoa')
                    ->get();

                foreach ($matriculas as $m) {
                    if ($m->pessoa) {
                        $pessoasComTurma->push((object) [
                            'pessoa' => $m->pessoa,
                            'turma' => $turma,
                        ]);
                    }
                }
            }
        } else {
            if (empty($pessoaIds)) {
                Notification::make()
                    ->title('Erro de Validação')
                    ->body('Selecione pelo menos uma pessoa para gerar os crachás.')
                    ->danger()
                    ->send();

                return null;
            }

            $pessoas = Pessoa::whereIn('id', $pessoaIds)->get();
            foreach ($pessoas as $pessoa) {
                $pessoasComTurma->push((object) [
                    'pessoa' => $pessoa,
                    'turma' => null,
                ]);
            }
        }

        if ($pessoasComTurma->isEmpty()) {
            Notification::make()
                ->title('Aviso')
                ->body('Nenhuma pessoa ativa encontrada para os critérios selecionados.')
                ->warning()
                ->send();

            return null;
        }

        try {
            $pdf = TemplateCrachaV2Service::gerarPdf($template, $pessoasComTurma);

            return response()->streamDownload(
                fn () => print ($pdf->output()),
                'crachas_lote_v2.pdf',
                ['Content-Type' => 'application/pdf']
            );
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erro ao gerar crachás')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }
}
