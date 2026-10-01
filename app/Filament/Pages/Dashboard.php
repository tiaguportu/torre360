<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Support\HelpContent;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use HasAjudaAction;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Início (Painel)', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🏠', 'Início', 'Visão geral do que precisa da sua atenção hoje.')
            ->secao('🎯 O que você encontra aqui?', [
                ['📊', 'Indicadores e gráficos', 'Resumos de alunos, matrículas, captação e outros dados do seu perfil.'],
                ['📅', 'Calendários', 'Aulas, preceptorias e retornos de contato agendados.'],
                ['⏳', 'Pendências', 'Itens que aguardam ação, como frequência não lançada, contratos e questionários pendentes.'],
            ])
            ->secao('🧭 Como navegar?', [
                ['📂', 'Menu lateral', 'Os grupos reúnem as telas por assunto; cada tela tem seu próprio botão de Ajuda.'],
                ['🔎', 'Busca global', 'Use a busca no topo para achar registros rapidamente.'],
            ])
            ->dica('Os blocos exibidos dependem das permissões do seu perfil; se faltar algum, fale com o administrador.');
    }
}
