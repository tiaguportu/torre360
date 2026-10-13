<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoAulas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanoAulas\Concerns\HasCopilotoPlanoAulaAction;
use App\Filament\Resources\PlanoAulas\PlanoAulaResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreatePlanoAula extends CreateRecord
{
    use HasAjudaAction;
    use HasCopilotoPlanoAulaAction;

    protected static string $resource = PlanoAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCopilotoPlanoAulaAction(),
            $this->ajudaAction('Novo Plano de Aula', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📝', 'Novo Plano de Aula', 'Cadastro de planejamento de sequência didática.')
            ->secao('🎯 Funcionalidades Disponíveis', [
                $user->can('GerarComCopiloto:PlanoAula') ? ['✨', 'Criar com Copiloto IA', 'Gera automaticamente objetivos, metodologia ativa e cronograma alinhados à BNCC.'] : null,
                ['📚', 'Habilidades BNCC', 'Vincule as habilidades pedagógicas oficiais previstas para a aula.'],
                ['🎯', 'Objetivos', 'Descreva o que os estudantes deverão alcançar.'],
            ])
            ->alerta('Sugestões geradas pela IA devem ser revisadas e validadas pelo docente antes do salvamento.')
            ->dica('Após a realização da aula, execute o plano para transformá-lo em registro real no diário de classe.');
    }
}
