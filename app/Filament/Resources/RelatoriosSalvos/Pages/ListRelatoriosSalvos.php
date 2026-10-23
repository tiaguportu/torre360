<?php

declare(strict_types=1);

namespace App\Filament\Resources\RelatoriosSalvos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\RelatoriosSalvos\RelatorioSalvoResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRelatoriosSalvos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = RelatorioSalvoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Meus Relatórios', HelpContent::make('📑', 'Relatórios configuráveis', 'Monte o relatório que você precisa e receba pronto na hora que quiser.')
                ->passos('🔁 Como usar', [
                    'Clique em "Novo relatório", escolha de onde vêm os dados e marque as colunas.',
                    'Use os filtros para reduzir (um período, uma turma, uma situação) e escolha a ordem e o formato.',
                    'Use "Gerar agora" para receber o arquivo na hora, ou ligue o agendamento para o sistema gerar sozinho e avisar quem você escolher.',
                    'Abra o relatório para ver o histórico das execuções e baixar os arquivos.',
                ])
                ->secao('📌 Bom saber', [
                    ['🔒', 'Quem vê os dados', 'Você escolhe entre as fontes que já pode ver nas telas do sistema. Quem recebe o aviso também precisa poder ver esses dados.'],
                    ['✉️', 'Aviso por e-mail', 'O e-mail leva só o link. O arquivo se baixa dentro do sistema, com login, e fica guardado por alguns dias.'],
                    ['⏱️', 'Hora do agendamento', 'O agendador roda de 5 em 5 minutos: o arquivo sai até 5 minutos depois da hora marcada.'],
                ])
                ->alerta('Relatório com nome, nascimento ou outro dado de pessoas é dado pessoal (LGPD): escolha só as colunas de que precisa e não compartilhe o arquivo fora da finalidade.')),
        ];
    }
}
