<?php

declare(strict_types=1);

namespace App\Filament\Resources\CicloAvaliacaoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CicloAvaliacaoDocentes\CicloAvaliacaoDocenteResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateCicloAvaliacaoDocente extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = CicloAvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo Ciclo de Avaliação Docente', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🔄', 'Novo Ciclo de Avaliação Docente', 'Abertura de período de avaliação de desempenho de professores.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['📆', 'Prazos de Vigência', 'Defina as datas de início e término das avaliações e autoavaliações.'],
                ['🎯', 'Parâmetros', 'Configure as diretrizes e regras aplicadas ao período avaliativo.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:CicloAvaliacaoDocente') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:CicloAvaliacaoDocente') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:CicloAvaliacaoDocente') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
