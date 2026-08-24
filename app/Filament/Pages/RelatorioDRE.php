<?php

namespace App\Filament\Pages;

use App\Services\DREService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use UnitEnum;

class RelatorioDRE extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?string $title = 'Demonstrativo de Resultados (DRE)';

    protected static ?string $slug = 'financeiro/dre';

    public ?array $data = [];

    public ?array $dreData = null;

    public function mount(): void
    {
        $this->getSchema('content')->fill([
            'data_inicio' => now()->startOfMonth()->format('Y-m-d'),
            'data_fim' => now()->endOfMonth()->format('Y-m-d'),
        ]);

        $this->generateDRE();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Filtros do Relatório')
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    DatePicker::make('data_inicio')
                                        ->label('Data Início')
                                        ->required()
                                        ->live(),
                                    DatePicker::make('data_fim')
                                        ->label('Data Fim')
                                        ->required()
                                        ->live(),
                                ]),
                        ])
                        ->compact(),
                ])->action(fn () => $this->generateDRE()),

                View::make('filament.pages.relatorio-d-r-e-results')
                    ->viewData(fn () => ['dreData' => $this->dreData]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Atualizar Relatório')
                ->icon('heroicon-m-arrow-path')
                ->action('generateDRE'),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Demonstrativo de Resultados (DRE)')
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
        $html = '<p>O <strong>Demonstrativo de Resultados (DRE)</strong> apresenta um resumo financeiro consolidado, mostrando receitas, despesas e o resultado do período selecionado.</p>';

        $html .= '<h3>Como usar?</h3><ul>';
        $html .= '<li><strong>Data Início / Data Fim:</strong> Defina o intervalo de datas que deseja analisar.</li>';
        $html .= '<li><strong>Atualizar Relatório:</strong> Recalcula o DRE com base no período informado nos filtros.</li>';
        $html .= '</ul>';

        return $html;
    }

    public function generateDRE(): void
    {
        $formData = $this->getSchema('content')->getState();

        $service = app(DREService::class);
        $this->dreData = $service->generate($formData['data_inicio'], $formData['data_fim']);
    }
}
