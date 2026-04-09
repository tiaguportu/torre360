<?php

namespace App\Filament\Pages;

use App\Services\DREService;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class RelatorioDRE extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string $view = 'filament.pages.relatorio-d-r-e';

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

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
        ];
    }

    public function generateDRE(): void
    {
        $formData = $this->getSchema('content')->getState();
        
        $service = app(DREService::class);
        $this->dreData = $service->generate($formData['data_inicio'], $formData['data_fim']);
    }
}
