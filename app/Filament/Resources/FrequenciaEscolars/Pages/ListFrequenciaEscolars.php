<?php

namespace App\Filament\Resources\FrequenciaEscolars\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\FrequenciaEscolars\FrequenciaEscolarResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFrequenciaEscolars extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = FrequenciaEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Frequências Escolares', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('✅', 'Frequências Escolares', 'Consulta e ajuste dos registros individuais de presença.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja a matrícula, a aula do cronograma e a situação de cada registro.'],
                $user->can('Create:FrequenciaEscolar') ? ['🆕', 'Novo Registro', 'Lance manualmente a frequência de uma matrícula em uma aula.'] : null,
                $user->can('Update:FrequenciaEscolar') ? ['✏️', 'Editar', 'Corrija a situação de um registro já lançado.'] : null,
            ])
            ->dica('Para lançar a chamada de uma turma inteira, use Calendário e Horários → Cronogramas de Aulas → Lançar Frequência.');
    }
}
