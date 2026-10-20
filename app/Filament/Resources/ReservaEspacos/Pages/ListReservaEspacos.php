<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReservaEspacos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ReservaEspacos\ReservaEspacoResource;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReservaEspacos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ReservaEspacoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova reserva'),
            Action::make('ocupacao')
                ->label('Ver ocupação dos espaços')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(fn (): string => ReservaEspacoResource::getUrl('ocupacao')),
            $this->ajudaAction('Reservas de Espaços', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📅', 'Reservas de Espaços', 'Agendamento pontual do auditório, da quadra, dos laboratórios e de outros espaços, fora da grade horária fixa.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja todas as reservas por data, espaço e situação. Por padrão aparecem as de hoje em diante; use os filtros para ver o passado ou só as suas.'],
                $user->can('Create:ReservaEspaco') ? ['🆕', 'Nova reserva', 'Escolha o espaço, a data e o horário. O sistema avisa na hora se o horário já está ocupado por outra reserva ou pela grade horária.'] : null,
                ['🗓️', 'Ver ocupação', 'Mostra, dia a dia, o que já ocupa um espaço (reservas e aulas da grade) antes de você pedir.'],
                $user->can('Update:ReservaEspaco') ? ['✏️', 'Editar / Cancelar', 'Você altera e cancela as suas reservas. Mudar o horário de um espaço com aprovação devolve o pedido para análise.'] : null,
                $user->can('Confirmar:ReservaEspaco') ? ['✅', 'Confirmar / Recusar', 'Em espaços que exigem aprovação, confirme o pedido ou cancele-o com uma justificativa.'] : null,
            ])
            ->alerta('Um pedido aguardando aprovação já segura o horário: ninguém consegue reservar o mesmo espaço no mesmo horário até ele ser confirmado ou cancelado.')
            ->dica('Para exigir aprovação num espaço (ex.: auditório), ative "Reservas exigem aprovação" no cadastro da Sala.');
    }
}
