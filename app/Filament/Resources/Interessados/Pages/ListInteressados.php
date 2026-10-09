<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Interessados\Actions\ImportarLeadIaAction;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Filament\Widgets\CrmFollowUpCalendarWidget;
use App\Services\ContadoresCrm;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListInteressados extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = InteressadoResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            CrmFollowUpCalendarWidget::make(),
        ];
    }

    public function getTabs(): array
    {
        $corteQuente = (int) config('lead_score.faixas_cor.quente', 70);
        $quentes = fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q
            ->where('temperatura', 'quente')
            ->orWhere('lead_score', '>=', $corteQuente));

        // Os 6 contadores (antes 6 consultas por render, vários com `whereHas` aninhado) vêm do cache
        // de `ContadoresCrm`, descartado quando um lead, contato ou etapa do funil é gravado.
        $contadores = ContadoresCrm::abas(fn () => InteressadoResource::getEloquentQuery(), auth()->id());

        return [
            'todos' => Tab::make('Todos')
                ->icon('heroicon-o-users')
                ->badge($contadores['todos']),
            'precisa_contato' => Tab::make('Precisa de contato')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query->ativos()->precisaContato())
                ->badge($contadores['precisa_contato'])
                ->badgeColor('danger'),
            'estagnados' => Tab::make('Estagnados')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->ativos()->estagnados())
                ->badge($contadores['estagnados'])
                ->badgeColor('warning'),
            'quentes' => Tab::make('Quentes')
                ->icon('heroicon-o-fire')
                ->modifyQueryUsing(fn (Builder $query) => $quentes($query->ativos()))
                ->badge($contadores['quentes'])
                ->badgeColor('success'),
            'ativos' => Tab::make('Em andamento')
                ->icon('heroicon-o-arrow-path')
                ->modifyQueryUsing(fn (Builder $query) => $query->ativos())
                ->badge($contadores['ativos'])
                ->badgeColor('info'),
            'finalizados' => Tab::make('Finalizados')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('status', fn (Builder $q) => $q->where('is_final', true)))
                ->badge($contadores['finalizados'])
                ->badgeColor('gray'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportarLeadIaAction::make(),
            Action::make('termometroVagas')
                ->label('Termômetro de Vagas')
                ->icon('heroicon-o-chart-bar')
                ->color('warning')
                ->modalHeading('📊 Termômetro de Ocupação e Vagas por Série')
                ->modalWidth(Width::Large)
                ->modalContent(fn () => view('filament.crm.modal-termometro-vagas'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
            Action::make('kanban')
                ->label('Ver Kanban')
                ->icon('heroicon-o-view-columns')
                ->color('info')
                ->url(InteressadoResource::getUrl('kanban')),
            $this->ajudaAction('CRM (Interessados)', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        $canCreate = $user->can('Create:Interessado');
        $canUpdate = $user->can('Update:Interessado');
        $canDelete = $user->can('DeleteAny:Interessado');
        $canEmail = $user->can('Create:ComunicacaoEmMassa') && $user->can('Enviar:ComunicacaoEmMassa');
        $canPesos = $user->can('View:ConfiguracaoLeadScore');

        return HelpContent::make('🎯', 'CRM: Interessados', 'Central de prospecção da escola: acompanhe cada lead do primeiro contato até a matrícula.')
            ->secao('🧭 O que você encontra nesta tela', [
                ['🗂️', 'Abas de situação (topo)', 'Cada aba mostra um recorte da lista, com a quantidade de leads ao lado: Todos, Precisa de contato, Estagnados, Quentes, Em andamento e Finalizados.'],
                ['📋', 'Tabela de leads', 'Uma linha por interessado, com as informações principais já resumidas (veja "Como ler a tabela").'],
                ['🔎', 'Busca e filtros', 'A busca procura por nome ou telefone. Os filtros ficam recolhidos acima da tabela.'],
                ['📅', 'Calendário de follow-up (rodapé)', 'Agenda com os próximos contatos e visitas dos leads, filtrável por consultor, com opção de ver só os atrasados.'],
                ['🔘', 'Botões do topo', 'Novo (cadastro manual), Importar Lead com IA, Ver Kanban (o mesmo funil em formato de quadro) e Ajuda.'],
            ])
            ->secao('🗂️ Para que serve cada aba', [
                ['👥', 'Todos', 'Todos os leads cadastrados, sem filtro.'],
                ['⏰', 'Precisa de contato', 'Leads em andamento cuja data de próximo contato já passou. É a sua lista de prioridades do dia.'],
                ['🕸️', 'Estagnados', 'Leads em andamento sem nenhuma interação registrada há 7 dias ou mais. Risco de esfriar.'],
                ['🔥', 'Quentes', 'Leads em andamento que o consultor marcou como "Quente" ou que têm score alto (a partir do corte configurado, 70 por padrão).'],
                ['🔄', 'Em andamento', 'Leads que ainda não chegaram a um status final (nem matriculados, nem perdidos).'],
                ['🏁', 'Finalizados', 'Leads já encerrados: matriculados ou perdidos.'],
            ])
            ->secao('📋 Como ler a tabela', [
                ['👤', 'Interessado', 'Nome do responsável e, logo abaixo, o telefone. Clique no cabeçalho para ordenar por nome.'],
                ['🏷️', 'Status / Consultor', 'Etapa atual do lead no funil (badge colorido) e, abaixo, o consultor responsável.'],
                ['📊', 'Qualificação', 'O badge mostra o Score (0 a 100, calculado automaticamente: verde = alto, âmbar = médio, vermelho = baixo). Abaixo aparece a temperatura (Quente, Morno ou Frio), que é a percepção do consultor e também pesa no score.'],
                ['📆', 'Próximo contato', 'Data combinada para o próximo contato e quanto falta ou quanto está atrasado. Fica em vermelho, com ícone de alerta, quando está atrasado.'],
                ['📍', 'Origem', 'De onde o lead veio (site, indicação, Instagram etc.).'],
                ['🟥', 'Faixa vermelha na linha', 'Indica que o lead precisa de contato agora.'],
                ['⚙️', 'Colunas opcionais', 'Pelo ícone de colunas da tabela você pode exibir Telefone, Consultor, Campanha, Temperatura, Dias no funil, Valor estimado, Total de contatos, Sem interação, Distância, Transporte, Redes sociais e Data de criação.'],
            ])
            ->secao('⚡ Ações em cada linha', [
                ['💬', 'Atendimento (ícone de balão)', 'Registra um contato: tipo, relato, duração, resultado e a data do próximo contato. Atualiza o histórico, o score e o prazo do lead.'],
                ['🟢', 'WhatsApp', 'Abre o WhatsApp com uma mensagem pronta a partir de um modelo. Só aparece se o lead tem telefone.'],
                ['📤', 'Enviar ao consultor (ícone de compartilhar)', 'Abre o WhatsApp do consultor responsável com o lead já resumido: link direto para falar com o interessado e os últimos contatos registrados. Fica verde quando o consultor tem telefone cadastrado e amarelo quando não tem (nesse caso o WhatsApp abre sem destinatário e você escolhe o contato). Só aparece se o lead tem consultor.'],
                $canUpdate ? ['✏️', 'Editar (lápis)', 'Abre a ficha completa: dados do negócio, redes sociais, dependentes, histórico e visitas.'] : null,
                ['⋮', 'Menu "Mais ações"', 'Agendar visita, Matricular (abre o Assistente de Matrícula já preenchido) ou Marcar matriculado, Gerar link de pré-matrícula online (a família preenche responsáveis, alunos e endereço, e os dados chegam prontos no Assistente de Matrícula) e marcar como Perdido (com o motivo).'],
            ])
            ->secao('🔎 Filtros disponíveis', [
                ['🏷️', 'Status, Origem e Campanha', 'Aceitam mais de uma opção ao mesmo tempo.'],
                ['🧑‍💼', 'Consultor', 'Mostra apenas os leads de um consultor.'],
                ['⏰', 'Precisa de contato e Estagnado', 'Complementam as abas e podem ser combinados com elas.'],
                ['🌡️', 'Temperatura', 'Quente, morno ou frio.'],
            ])
            ->secao('📦 Ações em lote (selecione várias linhas)', [
                $canUpdate ? ['✏️', 'Editar em lote', 'Altere em conjunto Status, Consultor, Temperatura, Origem, Campanha, Próximo Contato, Distância ou Transporte dos leads selecionados. Campos em branco permanecem inalterados.'] : null,
                ['👥', 'Atribuir consultor', 'Define o consultor responsável de todos os leads selecionados de uma vez.'],
                ['📤', 'Enviar aos consultores (WhatsApp)', 'Mostra um botão do WhatsApp para cada consultor, já com a lista dos leads dele numa única mensagem. Leads sem consultor aparecem num aviso e ficam de fora.'],
                $canEmail ? ['✉️', 'Enviar comunicação por e-mail', 'Dispara um e-mail em massa para os selecionados (use [Nome] para personalizar). Só recebem quem tem e-mail e não pediu para ficar de fora.'] : null,
                $canDelete ? ['🗑️', 'Excluir', 'Remove os leads selecionados.'] : null,
            ])
            ->secao('➕ Como entram novos leads', [
                $canCreate ? ['🆕', 'Novo', 'Cadastro manual do lead.'] : null,
                ['✨', 'Importar Lead com IA', 'Cole uma mensagem ou anexe um print de conversa; a IA preenche responsável, alunos, redes sociais e temperatura, e já registra o primeiro contato no histórico. Ao terminar, você é levado direto para a edição do lead criado.'],
                ['🌐', 'Captação automática', 'Leads do formulário público e da landing page chegam sozinhos nesta lista.'],
            ])
            ->passos('🚀 Rotina sugerida', [
                'Abra a aba "Precisa de contato" e atenda primeiro os leads com faixa vermelha.',
                'Registre cada conversa em "Atendimento", definindo a data do próximo contato.',
                'Use "Agendar visita" quando o responsável quiser conhecer a escola.',
                'Revise a aba "Estagnados" para retomar leads que ficaram sem interação.',
                'Quando fechar, use "Matricular"; se desistir, marque como "Perdido" informando o motivo.',
            ])
            ->secao('🧮 Sobre o Score', [
                ['📈', 'De onde vem', 'Soma automática de fatores: percepção do consultor (maior peso), perfil da família, engajamento e origem do lead. Atualiza quando o lead é salvo ou recebe um atendimento, e diariamente.'],
                $canPesos ? ['🎚️', 'Ajustar os pesos', 'Em CRM / Comercial, Pesos do Lead Score, é possível mudar a importância de cada fator.'] : null,
            ])
            ->dica('Mantenha a temperatura atualizada na ficha do lead: ela é o fator de maior peso no score e ajuda a aba "Quentes" a refletir a realidade.')
            ->alerta('Leads finalizados (matriculados ou perdidos) não aparecem em "Precisa de contato", "Estagnados" nem "Quentes".');
    }
}
