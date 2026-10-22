<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstrumentoAvaliacaoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\InstrumentoAvaliacaoDocentes\InstrumentoAvaliacaoDocenteResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInstrumentoAvaliacaoDocente extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = InstrumentoAvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Editar Instrumento de Avaliação Docente', $this->getHelpContent()),
            DeleteAction::make(),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📋', 'Editar Instrumento de Avaliação Docente', 'Atualização de critérios e pesos das dimensões avaliativas.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['✏️', 'Critérios Avaliativos', 'Modifique os enunciados e orientações de pontuação dos critérios.'],
                ['⚖️', 'Pesos', 'Ajuste a relevância percentual de cada dimensão no cálculo final.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:InstrumentoAvaliacaoDocente') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:InstrumentoAvaliacaoDocente') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:InstrumentoAvaliacaoDocente') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
