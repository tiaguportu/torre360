<?php

declare(strict_types=1);

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Portal\Concerns\InteractsWithMatriculaSelecionada;
use App\Models\ItemListaMaterial;
use App\Models\ListaMaterialEscolar;
use App\Services\ListaMaterialService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Portal da Família: lista de material escolar publicada para a série e o período do aluno selecionado.
 */
class ListaDeMaterial extends Page
{
    use HasAjudaAction;
    use InteractsWithMatriculaSelecionada;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Lista de Material';

    protected static ?string $navigationLabel = 'Lista de Material';

    protected static ?string $slug = 'lista-de-material';

    protected string $view = 'filament.portal.pages.lista-de-material';

    public function mount(): void
    {
        $this->matriculaId = $this->getMatriculaSelecionada()?->id;
    }

    public function getLista(): ?ListaMaterialEscolar
    {
        $matricula = $this->getMatriculaSelecionada();

        return $matricula ? app(ListaMaterialService::class)->listaPublicadaDoAluno($matricula) : null;
    }

    /**
     * @return Collection<string, Collection<int, ItemListaMaterial>>
     */
    public function getGrupos(?ListaMaterialEscolar $lista): Collection
    {
        return $lista ? app(ListaMaterialService::class)->itensPorCategoria($lista) : collect();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Lista de Material', HelpContent::make('🎒', 'Lista de Material', 'O que o aluno precisa para o ano, publicado pela escola.')
                ->secao('📖 Como usar', [
                    ['👧', 'Escolha o aluno', 'Se você acompanha mais de um aluno, use o seletor no alto da página.'],
                    ['🛒', 'Compre onde preferir', 'A escola não exige marca, loja nem kit fechado. Reaproveite o que o aluno já tem em bom estado.'],
                    ['📄', 'Baixe o PDF', 'Leve a lista impressa ou no celular para a papelaria.'],
                ])
                ->alerta('Material de uso coletivo da escola (limpeza, higiene, papelaria administrativa) é custeado pela mensalidade e não deve constar na lista. Se encontrar algo assim, avise a secretaria.')),
        ];
    }
}
