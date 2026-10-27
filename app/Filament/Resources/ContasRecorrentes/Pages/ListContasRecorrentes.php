<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContasRecorrentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ContasRecorrentes\ContaRecorrenteResource;
use App\Services\ContaRecorrenteService;
use App\Support\HelpContent;
use App\Support\PermissaoAcao;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListContasRecorrentes extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ContaRecorrenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerar')
                ->label('Gerar contas agora')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->authorize(PermissaoAcao::qualquer('Create:ContaRecorrente'))
                ->requiresConfirmation()
                ->modalHeading('Gerar as contas a pagar')
                ->modalDescription('A rotina já roda todo dia de manhã. Use este botão depois de cadastrar ou alterar uma despesa para ver as contas na hora. Conta que já existe no mês não é repetida.')
                ->action(function (): void {
                    $resultado = app(ContaRecorrenteService::class)->gerar();

                    Notification::make()->title($resultado['geradas'].' conta(s) gerada(s)')->body($resultado['ja_existiam'].' já existia(m) e foi(ram) mantida(s).')->success()->send();
                }),
            CreateAction::make()->label('Nova despesa recorrente'),
            $this->ajudaAction('Contas recorrentes', HelpContent::make('🔁', 'Contas recorrentes', 'Cadastre uma vez a despesa que se repete e deixe o sistema criar a conta a pagar de cada mês.')
                ->passos('🔁 Como usar', [
                    'Cadastre a despesa com a periodicidade, o dia do vencimento, o valor e a vigência.',
                    'Todo dia de manhã a rotina cria a conta a pagar dos vencimentos que entraram na antecedência escolhida.',
                    'Se o valor varia (energia, água), marque "valor estimado": a conta nasce "a revisar" e quem paga confere a fatura.',
                    'No dia do reajuste do contrato, use "Registrar reajuste": o valor novo vale daí em diante.',
                ])
                ->secao('📌 Bom saber', [
                    ['🛡️', 'Sem duplicar', 'A mesma despesa não gera duas contas no mesmo mês, nem se alguém rodar a rotina de novo.'],
                    ['🔭', 'Fluxo projetado', 'Os vencimentos que ainda não viraram conta entram na projeção do caixa dos próximos 90 dias.'],
                    ['🔔', 'Minha Mesa', 'Reajuste de contrato próximo e conta de valor estimado a revisar aparecem lá.'],
                ])),
        ];
    }
}
