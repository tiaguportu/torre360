<?php

namespace App\Filament\Resources\Matriculas\Pages;

use App\Enums\SituacaoMatricula;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Filament\Resources\Matriculas\Widgets\MatriculasResumoStats;
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

    protected function getHeaderWidgets(): array
    {
        return [
            MatriculasResumoStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return ['default' => 2, 'lg' => 4];
    }

    public function getTabs(): array
    {
        $totais = MatriculaResource::getEloquentQuery()
            ->toBase()
            ->reorder()
            ->selectRaw('situacao, count(*) as total')
            ->groupBy('situacao')
            ->pluck('total', 'situacao');

        $totalDe = fn (SituacaoMatricula $situacao): int => (int) ($totais[$situacao->value] ?? 0);

        // Abas por situação; as pouco usadas só aparecem quando há matrículas nelas.
        $abaDeSituacao = fn (SituacaoMatricula $situacao, string $rotulo, bool $ocultarSeVazia = false): Tab => Tab::make($rotulo)
            ->icon($situacao->getIcon())
            ->modifyQueryUsing(fn (Builder $query) => $query->where('situacao', $situacao))
            ->badge($totalDe($situacao))
            ->badgeColor($situacao->getColor())
            ->hidden($ocultarSeVazia && $totalDe($situacao) === 0);

        return [
            'ativas' => $abaDeSituacao(SituacaoMatricula::ATIVA, 'Ativas'),
            'pendentes' => $abaDeSituacao(SituacaoMatricula::PENDENTE, 'Pendentes'),
            'com_pendencias' => Tab::make('Com pendências')
                ->icon('heroicon-m-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('situacao', [SituacaoMatricula::ATIVA, SituacaoMatricula::PENDENTE])
                    ->comPendencias())
                ->badge(MatriculaResource::getEloquentQuery()
                    ->reorder()
                    ->whereIn('situacao', [SituacaoMatricula::ATIVA, SituacaoMatricula::PENDENTE])
                    ->comPendencias()
                    ->count())
                ->badgeColor('danger'),
            'trancadas' => $abaDeSituacao(SituacaoMatricula::TRANCADA, 'Trancadas', ocultarSeVazia: true),
            'concluidas' => $abaDeSituacao(SituacaoMatricula::CONCLUIDA, 'Concluídas', ocultarSeVazia: true),
            'canceladas' => $abaDeSituacao(SituacaoMatricula::CANCELADA, 'Canceladas'),
            'reserva' => $abaDeSituacao(SituacaoMatricula::RESERVA, 'Reserva', ocultarSeVazia: true),
            'evasao' => $abaDeSituacao(SituacaoMatricula::EVASAO, 'Evasão', ocultarSeVazia: true),
            'todas' => Tab::make('Todas')
                ->icon('heroicon-m-queue-list')
                ->badge((int) $totais->sum()),
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
            ->secao('🧭 O que você encontra nesta tela', [
                ['📊', 'Cartões de resumo (topo)', 'Mostram, para o que está na lista no momento (aba, busca e filtros), quantas matrículas há, quantas têm pendências, quantas estão sem responsável e quantas estão sem contrato.'],
                ['🗂️', 'Abas de situação', 'Ativas (aba inicial), Pendentes, Com pendências, Trancadas, Concluídas, Canceladas, Reserva, Evasão e Todas. O número em cada aba mostra quantas matrículas há. Trancadas, Concluídas, Reserva e Evasão só aparecem quando existem matrículas nessa situação.'],
                ['📋', 'Tabela de matrículas', 'Uma linha por matrícula, com as informações principais já resumidas (veja "Como ler a tabela").'],
                ['🔎', 'Busca e filtros', 'A busca procura pelo nome do aluno ou da turma. Os filtros ficam recolhidos acima da tabela: Curso, Turma, Período Letivo, Pendências e Contrato.'],
                $canCreate ? ['👥', 'Matrícula em Lote', 'Use o botão "Matrícula em Lote" para matricular vários alunos de uma vez em uma turma, definindo a situação.'] : null,
            ])
            ->secao('🗂️ Para que serve cada aba', [
                ['✅', 'Ativas', 'Matrículas em andamento. É a aba que abre por padrão.'],
                ['⏳', 'Pendentes', 'Matrículas em processo, geralmente aguardando documentação ou pagamento.'],
                ['🚨', 'Com pendências', 'Matrículas ativas ou pendentes que têm algum problema a resolver: aluno sem responsável, cadastro incompleto ou documentos obrigatórios faltando/rejeitados. É a sua lista de trabalho da secretaria.'],
                ['📚', 'Demais abas', 'Trancadas, Concluídas, Canceladas, Reserva e Evasão agrupam as matrículas por situação. "Todas" mostra tudo, sem recorte.'],
            ])
            ->secao('📋 Como ler a tabela', [
                ['👤', 'Aluno', 'Nome do aluno e, logo abaixo, a turma e o curso. Clique no nome para abrir a ficha da pessoa (se você tiver permissão).'],
                ['🏷️', 'Situação', 'Badge colorido com a situação da matrícula.'],
                ['⚠️', 'Pendências', 'Badges com cada tipo de pendência: Sem responsável, Cadastro incompleto, N documentos faltando e N documentos rejeitados. "Em dia" (verde) indica que não há nada a resolver. Passe o mouse para ver o resumo e clique para abrir o detalhe, com links para corrigir.'],
                ['🗓️', 'Período Letivo', 'Período ao qual a matrícula pertence.'],
                ['📄', 'Contrato', 'Ícone verde quando a matrícula já tem contrato gerado; cinza quando ainda não tem.'],
                ['⚙️', 'Colunas opcionais', 'Pelo ícone de colunas da tabela você pode exibir Turma (para ordenar por ela), Data de Ativação, Data de Desativação, Criada em e Atualizada em.'],
            ]);

        $conteudo->secao('⚡ Ações em cada matrícula', [
            $canUpdate ? ['✏️', 'Editar (lápis)', 'Altere dados da matrícula, como turma e situação.'] : null,
            $canDocumentos ? ['📎', 'Documentos (clipe)', 'Gerencia o envio dos documentos obrigatórios. O ícone fica vermelho, com o número de documentos faltando, quando há pendências.'] : null,
            ['⋮', 'Menu "Mais ações"', 'Agrupa as demais ações, separadas por assunto. Só aparecem as que se aplicam à matrícula e ao seu perfil.'],
            $canBoletim ? ['📄', 'Boletim (no menu)', 'Abre o boletim escolar do aluno (disponível apenas se houver notas).'] : null,
            $canAvisarPendencia ? ['📧', 'Avisar pendência por e-mail (no menu)', 'Envia um e-mail automático aos responsáveis listando os documentos que faltam ou foram rejeitados. A confirmação mostra os destinatários e a data do último aviso.'] : null,
            $canAvisarPreceptoria ? ['🤝', 'Avisar preceptoria por e-mail (no menu)', 'Envia um convite para agendar a preceptoria quando há horários disponíveis e o aluno ainda não agendou.'] : null,
            ['📝', 'Gerar contrato (no menu)', 'Cria o contrato da matrícula, com valor R$ 0,00, vinculando os responsáveis do aluno como responsáveis financeiros. Só aparece quando a matrícula não tem contrato e o aluno tem responsável.'],
        ]);

        $conteudo->secao('🔎 Filtros disponíveis', [
            ['🎓', 'Curso, Turma e Período Letivo', 'Turma e Período Letivo aceitam mais de uma opção ao mesmo tempo.'],
            ['⚠️', 'Pendências', 'Escolha um ou mais tipos (Sem responsável, Cadastro incompleto, Documentos faltando, Documentos rejeitados). A lista mostra as matrículas que têm qualquer um dos tipos escolhidos.'],
            ['📄', 'Contrato', 'Mostra somente matrículas com contrato ou somente as sem contrato.'],
        ]);

        if ($canUpdate || $canAvisarPendencia) {
            $conteudo->secao('📦 Ações em lote', [
                ['☑️', 'Seleção múltipla', 'Marque vários registros na lista para realizar ações coletivas: avisar pendências, avisar preceptoria, editar em lote e excluir.'],
            ]);
        }

        return $conteudo
            ->dica('Comece pela aba "Com pendências" e use o filtro "Pendências" para tratar um tipo de problema de cada vez.')
            ->alerta('Documento rejeitado continua contando como pendência até que um novo arquivo seja enviado e aprovado.');
    }
}
