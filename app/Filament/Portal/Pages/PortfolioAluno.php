<?php

declare(strict_types=1);

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Portal\Concerns\InteractsWithMatriculaSelecionada;
use App\Models\ParecerDescritivo;
use App\Models\PortfolioItem;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Portal da Família: pareceres descritivos PUBLICADOS e evidências do portfólio COMPARTILHADAS do aluno selecionado.
 * Nunca mostra rascunho, nem item não compartilhado, nem material em que aparecem outros alunos.
 */
class PortfolioAluno extends Page
{
    use HasAjudaAction;
    use InteractsWithMatriculaSelecionada;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Portfólio e Pareceres';

    protected static ?string $navigationLabel = 'Portfólio e Pareceres';

    protected static ?string $slug = 'portfolio';

    protected string $view = 'filament.portal.pages.portfolio-aluno';

    public function mount(): void
    {
        $this->matriculaId = $this->getMatriculaSelecionada()?->id;
    }

    /**
     * @return Collection<int, ParecerDescritivo>
     */
    public function getPareceres(): Collection
    {
        $matricula = $this->getMatriculaSelecionada();

        if (! $matricula) {
            return collect();
        }

        return ParecerDescritivo::query()
            ->publicados()
            ->where('matricula_id', $matricula->id)
            ->with('etapaAvaliativa')
            ->orderByDesc('publicado_em')
            ->get();
    }

    /**
     * @return Collection<int, PortfolioItem>
     */
    public function getItens(): Collection
    {
        $matricula = $this->getMatriculaSelecionada();

        if (! $matricula) {
            return collect();
        }

        return PortfolioItem::query()
            ->visiveisParaFamilia()
            ->where('matricula_id', $matricula->id)
            ->with('habilidades')
            ->orderByDesc('data_registro')
            ->orderByDesc('id')
            ->limit(60)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Portfólio e Pareceres', HelpContent::make('🖼️', 'Portfólio e Pareceres', 'Acompanhe o que o seu filho(a) produziu e o parecer dos professores.')
                ->secao('🎯 O que você vê aqui', [
                    ['📝', 'Pareceres', 'O texto de acompanhamento de cada etapa, depois de revisado e publicado pela escola.'],
                    ['🖼️', 'Portfólio', 'Fotos e trabalhos que a escola escolheu compartilhar com a família, com as habilidades que cada um demonstra.'],
                ])
                ->alerta('Este conteúdo é só da sua família. Não compartilhe nas redes sociais imagens que mostrem outras crianças.')),
        ];
    }
}
