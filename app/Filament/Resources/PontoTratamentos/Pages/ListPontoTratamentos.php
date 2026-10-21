<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoTratamentos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PontoTratamentos\PontoTratamentoResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPontoTratamentos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = PontoTratamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Pedir ajuste'),
            $this->ajudaAction('Ajustes de Ponto', HelpContent::make('✏️', 'Ajustes de Ponto', 'Corrija o dia sem mexer na marcação original.')
                ->secao('🎯 Tipos de ajuste', [
                    ['➕', 'Incluir marcação esquecida', 'Informe o horário em que você realmente entrou ou saiu.'],
                    ['🚫', 'Desconsiderar marcação indevida', 'Para um toque duplicado ou feito por engano. A marcação continua guardada, só deixa de contar.'],
                    ['🩺', 'Abonar tempo', 'Atestado, consulta ou folga combinada. O tempo abonado conta como trabalhado.'],
                ])
                ->passos('Fluxo', [
                    'O trabalhador pede o ajuste e explica o motivo.',
                    'Outra pessoa do RH aprova ou recusa (quem pediu não aprova o próprio pedido).',
                    'Aprovado, o dia é apurado de novo e o banco de horas é corrigido por um novo lançamento.',
                ])
                ->alerta('Não escreva diagnóstico no motivo. O texto é criptografado, mas basta informar que há atestado.')),
        ];
    }
}
