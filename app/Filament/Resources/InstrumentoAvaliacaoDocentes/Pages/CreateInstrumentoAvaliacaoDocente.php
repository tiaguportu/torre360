<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstrumentoAvaliacaoDocentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\InstrumentoAvaliacaoDocentes\InstrumentoAvaliacaoDocenteResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateInstrumentoAvaliacaoDocente extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = InstrumentoAvaliacaoDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo Instrumento de Avaliação Docente', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📋', 'Novo Instrumento de Avaliação Docente', 'Cadastro de formulário de avaliação contendo dimensões e critérios pedagógicos.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['📝', 'Identificação', 'Defina o título do instrumento e público-alvo (docentes, tutores, etc.).'],
                ['📊', 'Dimensões e Critérios', 'Estruture os eixos de análise e perguntas de avaliação.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:InstrumentoAvaliacaoDocente') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:InstrumentoAvaliacaoDocente') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:InstrumentoAvaliacaoDocente') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
