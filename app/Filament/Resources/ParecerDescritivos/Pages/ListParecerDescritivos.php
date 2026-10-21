<?php

declare(strict_types=1);

namespace App\Filament\Resources\ParecerDescritivos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ParecerDescritivos\ParecerDescritivoResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParecerDescritivos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ParecerDescritivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Novo parecer'),
            $this->ajudaAction('Pareceres Descritivos', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📝', 'Pareceres Descritivos', 'Texto de acompanhamento do aluno por etapa, escrito pelo professor e publicado pela coordenação.')
            ->secao('🎯 Fluxo', [
                ['✏️', 'Rascunho', 'O professor escreve o parecer do aluno na etapa, com base nos conceitos por habilidade e no portfólio.'],
                ['👀', 'Em revisão', 'Ao terminar, envia para a coordenação revisar.'],
                $user->can('Publicar:ParecerDescritivo') ? ['✅', 'Publicar', 'A coordenação publica e a família passa a ver o parecer no Portal.'] : ['✅', 'Publicado', 'Só a coordenação publica. Depois de publicado, o parecer não é editado sem ser reaberto.'],
                config('copiloto_pedagogico.parecer_ia_habilitado') && $user->can('GerarComIa:ParecerDescritivo') ? ['🤖', 'Rascunho com IA', 'Na edição, gere um rascunho a partir dos registros da etapa. É só um ponto de partida: revise tudo antes de enviar.'] : null,
            ])
            ->alerta('O parecer é do professor. A IA, quando ligada, só sugere um rascunho (sem o nome do aluno), e nada vai para a família sem a revisão da coordenação.')
            ->dica('Quem atua como professor vê apenas os pareceres dos alunos das próprias turmas.');
    }
}
