<?php

namespace App\Filament\Resources\DisponibilidadeVisitas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\DisponibilidadeVisitas\DisponibilidadeVisitaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDisponibilidadeVisitas extends ManageRecords
{
    use HasAjudaAction;

    protected static string $resource = DisponibilidadeVisitaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Novo horário'),
            $this->ajudaAction('Agenda de Visitas', $this->conteudoDaAjuda()),
        ];
    }

    private function conteudoDaAjuda(): HelpContent
    {
        return HelpContent::make('📅', 'Agenda de Visitas', 'Os horários em que cada consultor recebe visitas. A família escolhe um deles na página pública /visita.')
            ->passos('🚀 Como montar a agenda', [
                'Clique em "Novo horário" e escolha o consultor, o dia da semana e a faixa (por exemplo, quartas das 9h às 11h).',
                'Defina a duração de cada visita; a faixa é dividida em horários dessa duração.',
                'Ajuste a antecedência mínima (para não marcarem para daqui a uma hora) e até quantos dias à frente oferecer.',
            ])
            ->secao('ℹ️ O que o sistema faz sozinho', [
                ['🚫', 'Horários que somem', 'Dias não letivos e horários em que o consultor já tem visita marcada não são oferecidos.'],
                ['👥', 'Mais de um consultor', 'No mesmo horário, a visita fica com quem tem menos visitas naquele dia.'],
                ['📧', 'Avisos', 'A família recebe a confirmação com os links para trocar ou cancelar; o consultor é avisado no sino.'],
            ])
            ->dica('Desmarque "Oferecer este horário à família" para tirar uma faixa do ar sem apagá-la (férias, treinamento).');
    }
}
