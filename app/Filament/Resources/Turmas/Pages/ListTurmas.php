<?php

namespace App\Filament\Resources\Turmas\Pages;

use App\Filament\Resources\Turmas\TurmaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListTurmas extends ListRecords
{
    protected static string $resource = TurmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Turmas')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $canCreate = $user->can('Create:Turma');
        $canUpdate = $user->can('Update:Turma');
        $canViewBoletim = $user->can('Boletim:Matricula');

        $html = '<p>Nesta página você gerencia as turmas da instituição, vinculando-as a séries, etapas de ensino, períodos letivos, além de definir dados de mediação didática e tipo de turma.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Listagem:</strong> Visualize todas as turmas ativas, seus códigos, séries, turnos, Etapa de Ensino Agregada, Etapa de Ensino, tipo de mediação didático-pedagógica, tipo de turma e flag de Educação Especial.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Nova Turma:</strong> Crie uma nova turma definindo o nome, código, turno, capacidade, Etapa de Ensino Agregada (301, 302, 304, etc.), Etapa de Ensino vinculada, tipo de mediação (1-Presencial, 2-Semipresencial, 3-EAD), tipo de turma (4, 5, 6, 9), local de funcionamento diferenciado, flag de Educação Especial e Horários de Funcionamento por dia da semana.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Altere dados da turma ou encerre turmas antigas.</li>';
            $html .= '<li><strong>Editar em Lote (lote):</strong> Selecione duas ou mais turmas na tabela para alterar simultaneamente a Série, Turno, Etapa Agregada, Etapa de Ensino, Professor Conselheiro, Vagas, Carga Horária, Tipo de Avaliação, Mediação, Tipo de Turma, Local Diferenciado e Flag de Educação Especial.</li>';
            $html .= '<li><strong>Horários de Funcionamento em Lote (lote):</strong> Permite cadastrar ou substituir em lote os horários de funcionamento por dia da semana (Domingo a Sábado) para várias turmas selecionadas.</li>';
        }

        if ($canViewBoletim) {
            $html .= '<li><strong>Imprimir Boletins (tabela):</strong> Permite gerar e baixar em PDF os boletins de todos os alunos ativos de uma turma específica para a etapa selecionada.</li>';
            $html .= '<li><strong>Imprimir Boletins em Lote (lote):</strong> Permite selecionar várias turmas para gerar um único PDF com os boletins de todos os alunos ativos correspondentes.</li>';
        }

        $html .= '<li><strong>Vínculos:</strong> As turmas são essenciais para o processo de matrícula e lançamento de notas.</li>';
        $html .= '</ul>';

        return $html;
    }
}
