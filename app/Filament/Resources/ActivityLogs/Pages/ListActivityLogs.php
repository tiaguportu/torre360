<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ActivityLogResource::class;

    protected static ?string $title = 'Logs de Atividade';

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Logs de Atividade', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🕵️', 'Logs de Atividade', 'Trilha de auditoria: quem fez o quê no sistema.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja data, usuário responsável, descrição da ação, tipo de log e o registro afetado.'],
                ['🔎', 'Filtro por tipo', 'Filtre pelo tipo de log para isolar um assunto específico.'],
            ])
            ->alerta('Esta tela é restrita ao Super Administrador e é somente consulta: os registros de auditoria não podem ser alterados.', '🔒')
            ->dica('Use a data e o usuário para investigar uma alteração específica.');
    }
}
