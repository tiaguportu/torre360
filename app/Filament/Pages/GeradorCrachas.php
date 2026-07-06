<?php

namespace App\Filament\Pages;

use App\Enums\SituacaoMatricula;
use App\Models\Pessoa;
use App\Models\TemplateCracha;
use App\Models\TemplateCrachaV2;
use App\Models\TemplateCrachaV3;
use App\Models\Turma;
use App\Services\TemplateCrachaV2Service;
use App\Services\TemplateCrachaV3Service;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeradorCrachas extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Gerador de Crachás';

    protected static ?string $title = 'Gerador de Crachás';

    protected static ?string $slug = 'secretaria/gerador-crachas';

    protected string $view = 'filament.pages.gerador-crachas';

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
                Section::make('Parâmetros de Geração')
                    ->description('Selecione o modelo de crachá e defina as pessoas para quem deseja gerar.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('versao')
                                    ->label('Versão do Crachá')
                                    ->options([
                                        'v1' => 'Versão 1 (Editor Canvas / FabricJS)',
                                        'v2' => 'Versão 2 (SVG Edit)',
                                        'v3' => 'Versão 3 (Moveable)',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('template_id', null)),

                                Select::make('template_id')
                                    ->label('Modelo de Crachá')
                                    ->options(function (Get $get) {
                                        $versao = $get('versao');

                                        return match ($versao) {
                                            'v1' => TemplateCracha::pluck('nome', 'id'),
                                            'v2' => TemplateCrachaV2::pluck('nome', 'id'),
                                            'v3' => TemplateCrachaV3::pluck('nome', 'id'),
                                            default => [],
                                        };
                                    })
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (Get $get) => empty($get('versao'))),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('tipo_selecao')
                                    ->label('Selecionar Pessoas por')
                                    ->options([
                                        'turma' => 'Por Turma (Todos os Alunos Ativos)',
                                        'individual' => 'Seleção Individual (Livre)',
                                    ])
                                    ->default('individual')
                                    ->required()
                                    ->live(),

                                Select::make('turma_id')
                                    ->label('Turma')
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
                ->modalHeading('Ajuda: Gerador de Crachás')
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
        $user = auth()->user();
        $activeRole = session('active_role');

        $canViewV1 = $user->can('ViewAny:TemplateCracha');
        $canViewV2 = $user->can('ViewAny:TemplateCrachaV2');
        $canViewV3 = $user->can('ViewAny:TemplateCrachaV3');
        $canViewPessoas = $user->can('ViewAny:Pessoa');
        $canViewTurmas = $user->can('ViewAny:Turma');

        $html = '<p>Esta página permite realizar a geração centralizada de crachás em PDF de qualquer uma das três versões disponíveis no sistema (Versão 1, Versão 2 ou Versão 3).</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';

        $versoesDisponiveis = [];
        if ($canViewV1) {
            $versoesDisponiveis[] = 'Versão 1 (Canvas)';
        }
        if ($canViewV2) {
            $versoesDisponiveis[] = 'Versão 2 (SVG Edit)';
        }
        if ($canViewV3) {
            $versoesDisponiveis[] = 'Versão 3 (Moveable)';
        }

        if (! empty($versoesDisponiveis)) {
            $html .= '<li><strong>Parâmetros de Geração:</strong> Escolha entre as versões disponíveis para você ('.implode(', ', $versoesDisponiveis).') e selecione o modelo correspondente cadastrado.</li>';
        } else {
            $html .= '<li><strong>Parâmetros de Geração:</strong> <span class="text-danger">Você não possui permissão para visualizar nenhum dos modelos de crachá cadastrados.</span></li>';
        }

        if ($canViewTurmas || $canViewPessoas) {
            $html .= '<li><strong>Origem dos Crachás:</strong> Defina se deseja gerar os crachás ';
            $origens = [];
            if ($canViewTurmas) {
                $origens[] = 'para uma <strong>Turma inteira</strong> (apenas alunos com matrículas ativas)';
            }
            if ($canViewPessoas) {
                $origens[] = 'de forma <strong>Individual</strong> selecionando uma ou mais pessoas por busca';
            }
            $html .= implode(' ou ', $origens).'.</li>';
        }

        $html .= '<li><strong>Geração do PDF:</strong> Clique no botão de gerar para que o sistema renderize em lote o crachá das pessoas com as informações e foto preenchidas no padrão configurado e faça o download automático do PDF.</li>';
        $html .= '</ul>';

        return $html;
    }

    public function gerar(): ?StreamedResponse
    {
        $state = $this->form->getState();

        $versao = $state['versao'] ?? null;
        $templateId = $state['template_id'] ?? null;
        $tipoSelecao = $state['tipo_selecao'] ?? 'individual';
        $turmaId = $state['turma_id'] ?? null;
        $pessoaIds = $state['pessoa_ids'] ?? [];

        if (! $versao || ! $templateId) {
            Notification::make()
                ->title('Erro de Validação')
                ->body('Selecione a versão e o modelo do crachá.')
                ->danger()
                ->send();

            return null;
        }

        $template = match ($versao) {
            'v1' => TemplateCracha::find($templateId),
            'v2' => TemplateCrachaV2::find($templateId),
            'v3' => TemplateCrachaV3::find($templateId),
            default => null,
        };

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
            $turma = Turma::find($turmaId);
            if (! $turma) {
                Notification::make()
                    ->title('Erro')
                    ->body('Turma selecionada não encontrada.')
                    ->danger()
                    ->send();

                return null;
            }

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
            if ($versao === 'v1') {
                $layout = $template->dados_layout;
                $objects = $layout['objects'] ?? [];
                $backgroundImage = $layout['backgroundImage']['src'] ?? null;

                $crachaLargura = $template->largura * 0.75;
                $crachaAltura = $template->altura * 0.75;

                $pdf = DomPdf::loadView('pdf.cracha-lote', [
                    'pessoasComTurma' => $pessoasComTurma,
                    'objects' => $objects,
                    'backgroundImage' => $backgroundImage,
                    'crachaLargura' => $crachaLargura,
                    'crachaAltura' => $crachaAltura,
                ])->setPaper('a4', 'portrait');
            } elseif ($versao === 'v2') {
                $pdf = TemplateCrachaV2Service::gerarPdf($template, $pessoasComTurma);
            } else {
                $pdf = TemplateCrachaV3Service::gerarPdf($template, $pessoasComTurma);
            }

            return response()->streamDownload(
                fn () => print ($pdf->output()),
                'crachas_lote.pdf',
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
