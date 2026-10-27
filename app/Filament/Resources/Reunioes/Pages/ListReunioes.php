<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reunioes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Pages\MeusEncaminhamentos;
use App\Filament\Resources\Reunioes\ReuniaoResource;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReunioes extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ReuniaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova reunião'),
            Action::make('meus_encaminhamentos')
                ->label('Meus encaminhamentos')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->visible(fn (): bool => MeusEncaminhamentos::canAccess())
                ->url(fn (): string => MeusEncaminhamentos::getUrl()),
            $this->ajudaAction('Reuniões e atas', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('👥', 'Reuniões e atas', 'Conselho de classe, reunião pedagógica, devolutiva individual e formação: da pauta à ata, com os encaminhamentos de cada reunião.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja as reuniões que você organiza ou em que participa. Quem coordena também enxerga as dos outros, menos as sigilosas.'],
                $user->can('Create:Reuniao') ? ['🆕', 'Nova reunião', 'Marque data, hora, espaço e participantes. Cada convidado recebe um aviso e responde se vai. Escolher um espaço reserva o horário nele.'] : null,
                ['✅', 'Responder convite', 'Abra a reunião e diga se vai participar.'],
                ['🧑‍🏫', 'Conselho de classe', 'A pauta traz cada aluno da turma com nota, frequência, ocorrências e parecer. A turma delibera aluno por aluno, sem alterar nota nem matrícula.'],
                ['📝', 'Ata', 'Registre a presença, escreva a ata e feche. Depois de fechada ela não muda: correções entram como adendo. A ata fechada vai para o PDF e para a assinatura eletrônica.'],
                ['📌', 'Encaminhamentos', 'O que ficou combinado, com responsável e prazo. Cada responsável acompanha os seus em "Meus encaminhamentos".'],
            ])
            ->alerta('Reunião sigilosa e devolutiva individual só aparecem para quem organiza e para quem participa, e não mostram o assunto nas reservas de espaço, nos avisos nem na assinatura eletrônica.')
            ->dica('Uma ata realizada e não fechada aparece na Mesa de pendências depois de alguns dias.');
    }
}
