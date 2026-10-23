<?php

declare(strict_types=1);

namespace App\Filament\Resources\RelatoriosSalvos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\RelatoriosSalvos\RelatorioSalvoResource;
use App\Services\Relatorios\ConfiguracaoDeRelatorioService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRelatorioSalvo extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = RelatorioSalvoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            return app(ConfiguracaoDeRelatorioService::class)->normalizar($data, auth()->user());
        } catch (\DomainException $e) {
            Notification::make()->title('Revise o relatório')->body($e->getMessage())->danger()->persistent()->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo relatório', HelpContent::make('📑', 'Novo relatório', 'Escolha os dados, as colunas e, se quiser, o agendamento.')
                ->secao('🎯 Como preencher', [
                    ['🗂️', 'Dados de', 'A fonte dos dados. Ao trocar, as colunas e os filtros mudam.'],
                    ['☑️', 'Colunas', 'Marque o que precisa. Dado pessoal vem sinalizado: leve só o necessário.'],
                    ['🔎', 'Filtros e ordem', 'Reduza o relatório (um período, uma turma, uma situação) e escolha a ordem.'],
                    ['⏰', 'Agendamento', 'Todo dia, toda semana ou todo mês, na hora marcada. Quem recebe o aviso precisa poder ver os dados.'],
                ])),
        ];
    }
}
