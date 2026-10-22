<?php

declare(strict_types=1);

namespace App\Filament\Resources\ParecerDescritivos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ParecerDescritivos\ParecerDescritivoResource;
use App\Support\HelpContent;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewParecerDescritivo extends ViewRecord
{
    use HasAjudaAction;

    protected static string $resource = ParecerDescritivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Visualizar Parecer Descritivo', $this->getHelpContent()),
            EditAction::make(),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('👁️', 'Visualizar Parecer Descritivo', 'Consulta ao parecer descritivo cadastrado para o estudante.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['📄', 'Leitura do Parecer', 'Verifique o histórico descritivo pedagógico emitido.'],
                ['🖨️', 'Impressão e Exportação', 'Gere o documento oficial para anexar ao dossiê do estudante.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:ParecerDescritivo') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:ParecerDescritivo') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:ParecerDescritivo') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
