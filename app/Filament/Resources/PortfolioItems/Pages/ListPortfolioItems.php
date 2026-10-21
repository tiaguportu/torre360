<?php

declare(strict_types=1);

namespace App\Filament\Resources\PortfolioItems\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PortfolioItems\PortfolioItemResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPortfolioItems extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = PortfolioItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova evidência'),
            $this->ajudaAction('Portfólio do Aluno', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🖼️', 'Portfólio do Aluno', 'Evidências do que o aluno produziu e aprendeu, ligadas às habilidades da BNCC.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja as evidências por aluno, turma, habilidade e período. Quem atua como professor vê só os alunos das próprias turmas.'],
                $user->can('Create:PortfolioItem') ? ['🆕', 'Nova evidência', 'Registre uma produção, foto, áudio ou vídeo curto e marque as habilidades que ela demonstra.'] : null,
                ['👪', 'Compartilhar com a família', 'Marque o item para a família do aluno ver no Portal. Só ela vê.'],
                ['✍️', 'Parecer descritivo', 'As evidências da etapa alimentam o parecer descritivo do aluno.'],
            ])
            ->alerta('Foto, vídeo ou áudio em que aparecem outros alunos nunca pode ser compartilhado com a família de um aluno só (imagem de outras crianças). Use "Aparecem outros alunos" para marcar o material.')
            ->dica('Os arquivos ficam em área privada do sistema e só abrem para quem tem acesso ao aluno.');
    }
}
