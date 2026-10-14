<?php

namespace App\Filament\Resources\ListaEsperaMatriculas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ListaEsperaMatriculas\ListaEsperaMatriculaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListListaEsperaMatriculas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ListaEsperaMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Lista de Espera', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('⏳', 'Lista de Espera', 'Fila de pretendentes para turmas lotadas, notificados automaticamente quando abre vaga.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja aluno, turma, status e desde quando está na fila.'],
                $user->can('Create:ListaEsperaMatricula') ? ['🆕', 'Adicionar à fila', 'Registre um aluno/pretendente na lista de espera de uma turma lotada.'] : null,
                ['❌', 'Marcar Desistência', 'Encerre a espera quando a família desistir.'],
            ])
            ->dica('Quando uma matrícula daquela turma é cancelada, transferida ou excluída, o sistema notifica automaticamente o primeiro da fila (e marca a entrada como "Convertido" se ele for matriculado em seguida).');
    }
}
