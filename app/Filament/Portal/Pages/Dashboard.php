<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\Pessoa;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class Dashboard extends Page
{
    use HasAjudaAction;
    protected static string $routePath = '/';

    protected static ?int $navigationSort = -2;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Início';

    public static function getRoutePath(Panel $panel): string
    {
        return static::$routePath;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.portal.pages.dashboard')
                ->viewData(['pessoas' => $this->getPessoasAcessiveis()]),
        ]);
    }

    /**
     * @return Collection<int, Pessoa>
     */
    protected function getPessoasAcessiveis(): Collection
    {
        $pessoas = auth()->user()->pessoasAcessiveis();
        $pessoas->load('matriculas.turma', 'matriculas.periodoLetivo');

        // Mostra apenas os alunos (quem tem matrícula), não a ficha do próprio responsável.
        return $pessoas->filter(fn ($pessoa) => $pessoa->matriculas->isNotEmpty())->values();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Início', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🏠', 'Início', 'Um resumo dos alunos que você acompanha.')
            ->secao('🎯 O que você encontra aqui?', [
                ['👧', 'Seus alunos', 'Um cartão para cada aluno matriculado, com turma e período letivo.'],
                ['🧭', 'Atalhos', 'Use o menu lateral para ver notas, frequência, horários, financeiro e muito mais.'],
            ])
            ->dica('Se tiver mais de um aluno, cada um aparece separado no menu e no início.');
    }
}
