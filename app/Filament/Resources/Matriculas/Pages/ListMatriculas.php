<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Enums\SituacaoMatricula;
use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Models\Matricula;
use App\Models\Pessoa;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListMatriculas extends ListRecords
{
    protected static string $resource = MatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('matriculaEmLote')
                ->label('Matrícula em Lote')
                ->icon('heroicon-o-users')
                ->color('info')
                ->form([
                    Select::make('turma_id')
                        ->label('Turma')
                        ->relationship('turma', 'nome', fn ($query) => $query->whereNotNull('nome'))
                        ->required()
                        ->searchable()
                        ->preload(),
                    Select::make('aluno_ids')
                        ->label('Alunos')
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Pessoa::query()
                            ->where('nome', 'like', "%{$search}%")
                            ->whereHas('users', fn ($q) => $q->role('aluno'))
                            ->limit(50)
                            ->pluck('nome', 'id')
                            ->toArray()
                        )
                        ->getOptionLabelsUsing(fn (array $values): array => Pessoa::query()
                            ->whereIn('id', $values)
                            ->pluck('nome', 'id')
                            ->toArray()
                        )
                        ->required(),
                    Select::make('situacao')
                        ->label('Situação')
                        ->options(SituacaoMatricula::class)
                        ->required()
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data) {
                    foreach ($data['aluno_ids'] as $pessoaId) {
                        Matricula::create([
                            'pessoa_id' => $pessoaId,
                            'turma_id' => $data['turma_id'],
                            'situacao' => $data['situacao'],
                        ]);
                    }
                })
                ->successNotificationTitle('Matrículas criadas com sucesso!'),
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Matrículas')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => '
                                <p>Esta página permite gerenciar as matrículas dos alunos no sistema. Aqui você pode visualizar, filtrar e realizar ações em massa.</p>
                                <h3>O que você pode fazer aqui?</h3>
                                <ul>
                                    <li><strong>Listagem e Busca:</strong> Visualize todos os alunos matriculados. Use a barra de busca para encontrar alunos por nome ou os filtros para filtrar por Curso, Turma, Período Letivo ou Situação.</li>
                                    <li><strong>Matrícula em Lote:</strong> Use o botão "Matrícula em Lote" para matricular vários alunos de uma vez em uma turma específica.</li>
                                    <li><strong>Ações Individuais:</strong>
                                        <ul>
                                            <li><strong>Editar:</strong> Altere dados da matrícula (turma, situação, etc).</li>
                                            <li><strong>Boletim:</strong> Visualiza o boletim escolar do aluno (disponível apenas se houver notas).</li>
                                            <li><strong>Documentos:</strong> Gerencia o envio de documentos obrigatórios. O ícone fica vermelho se houver pendências.</li>
                                            <li><strong>Avisar Pendência:</strong> Envia um e-mail automático aos responsáveis listando os documentos que faltam.</li>
                                            <li><strong>Avisar Preceptoria:</strong> Envia um convite para agendamento de preceptoria quando houver disponibilidade.</li>
                                        </ul>
                                    </li>
                                    <li><strong>Ações em Lote:</strong> Selecione vários registros para atualizar a situação/turma em massa ou enviar avisos de pendência para todos os selecionados.</li>
                                </ul>
                                <p><small>Dica: Linhas com fundo avermelhado indicam alunos com documentos obrigatórios pendentes.</small></p>
                            ',
                        ]),
                ]),
        ];
    }
}
