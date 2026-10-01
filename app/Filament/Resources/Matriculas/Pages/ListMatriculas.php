<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Enums\SituacaoMatricula;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMatriculas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = MatriculaResource::class;

    public function getTabs(): array
    {
        $baseQuery = fn () => MatriculaResource::getEloquentQuery();

        return [
            'todas' => Tab::make('Todas')
                ->badge($baseQuery()->count()),
            'pendentes' => Tab::make('Pendentes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('situacao', SituacaoMatricula::PENDENTE))
                ->badge($baseQuery()->where('situacao', SituacaoMatricula::PENDENTE)->count())
                ->badgeColor('warning'),
            'ativas' => Tab::make('Ativas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('situacao', SituacaoMatricula::ATIVA))
                ->badge($baseQuery()->where('situacao', SituacaoMatricula::ATIVA)->count())
                ->badgeColor('success'),
            'canceladas' => Tab::make('Canceladas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('situacao', SituacaoMatricula::CANCELADA))
                ->badge($baseQuery()->where('situacao', SituacaoMatricula::CANCELADA)->count())
                ->badgeColor('danger'),
        ];
    }

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
            $this->ajudaAction('Gestão de Matrículas', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        $canCreate = $user->can('Create:Matricula');
        $canUpdate = $user->can('Update:Matricula');
        $canDocumentos = $user->can('Documentos:Matricula');
        $canAvisarPendencia = $user->can('AvisarPendencia:Matricula');
        $canAvisarPreceptoria = $user->can('AvisarPossibilidadePreceptoria:Matricula');
        $canBoletim = $user->can('Boletim:Matricula');

        $conteudo = HelpContent::make('🎓', 'Gestão de Matrículas', 'Acompanhe, filtre e atue nas matrículas dos alunos.')
            ->secao('🎯 O que você pode fazer aqui?', [
                ['📋', 'Listagem e busca', 'Veja todos os alunos matriculados. Busque por nome ou filtre por Curso, Turma, Período Letivo e Situação.'],
                ['🗂️', 'Abas de situação', 'Alterne entre Todas, Pendentes, Ativas e Canceladas; o número em cada aba mostra quantas matrículas há.'],
                $canCreate ? ['👥', 'Matrícula em Lote', 'Use o botão "Matrícula em Lote" para matricular vários alunos de uma vez em uma turma, definindo a situação.'] : null,
            ]);

        $conteudo->secao('⚡ Ações em cada matrícula', [
            $canUpdate ? ['✏️', 'Editar', 'Altere dados da matrícula, como turma e situação.'] : null,
            $canBoletim ? ['📄', 'Boletim', 'Abre o boletim escolar do aluno (disponível apenas se houver notas).'] : null,
            $canDocumentos ? ['📎', 'Documentos', 'Gerencia o envio dos documentos obrigatórios. O ícone fica vermelho se houver pendências.'] : null,
            $canAvisarPendencia ? ['📧', 'Avisar Pendência', 'Envia um e-mail automático aos responsáveis listando os documentos que faltam.'] : null,
            $canAvisarPreceptoria ? ['🤝', 'Avisar Preceptoria', 'Envia um convite para agendar a preceptoria quando houver disponibilidade.'] : null,
        ]);

        if ($canUpdate || $canAvisarPendencia) {
            $conteudo->secao('📦 Ações em lote', [
                ['☑️', 'Seleção múltipla', 'Marque vários registros na lista para realizar ações coletivas.'],
            ]);
        }

        if ($canDocumentos) {
            $conteudo->dica('Linhas com fundo avermelhado indicam alunos com documentos obrigatórios pendentes.', '🔴');
        }

        return $conteudo;
    }
}
