<?php

namespace Tests\Feature;

use App\Filament\Resources\Matriculas\MatriculaResource;
use App\Filament\Resources\Pessoas\PessoaResource;
use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles;
use Filament\Facades\Filament;
use ReflectionClass;
use Tests\TestCase;

/**
 * Varredura final: toda tela registrada na navegação dos painéis (admin e portal)
 * deve ter o botão de Ajuda. Uma tela nova na sidebar sem Ajuda faz este teste falhar.
 */
class AjudaCoberturaNavegacaoTest extends TestCase
{
    /**
     * Telas de pacotes de terceiros, sem ajuda própria (ver docs/ajuda_botoes_sidebar.md).
     */
    private const EXCECOES = [
        ListRoles::class, // Filament Shield → Roles (resource do pacote)
    ];

    private function temAjuda(string $classe): bool
    {
        $src = file_get_contents((new ReflectionClass($classe))->getFileName());

        return str_contains($src, "'ajuda'")
            || str_contains($src, 'ajudaAction(')
            || str_contains($src, 'ajudaCadastro(');
    }

    /**
     * @return array<int, string>
     */
    private function telasSemAjuda(string $painel): array
    {
        $panel = Filament::getPanel($painel);
        $faltando = [];

        foreach ($panel->getResources() as $resource) {
            if (! $resource::shouldRegisterNavigation()) {
                continue;
            }

            $paginas = $resource::getPages();
            $classe = ($paginas['index'] ?? array_values($paginas)[0])->getPage();

            if (! in_array($classe, self::EXCECOES, true) && ! $this->temAjuda($classe)) {
                $faltando[] = $classe;
            }
        }

        foreach ($panel->getPages() as $pagina) {
            if ($pagina::shouldRegisterNavigation() && ! $this->temAjuda($pagina)) {
                $faltando[] = $pagina;
            }
        }

        return $faltando;
    }

    public function test_todas_as_telas_do_painel_admin_tem_botao_de_ajuda(): void
    {
        $this->assertSame([], $this->telasSemAjuda('admin'), 'Telas do admin sem botão de Ajuda');
    }

    /**
     * A sidebar dinâmica de responsáveis/alunos (EnsureActiveRole) aponta para páginas internas
     * (editar, boletim, documentos, agendar) que não aparecem na navegação padrão.
     */
    public function test_destinos_da_sidebar_do_responsavel_tem_botao_de_ajuda(): void
    {
        $destinos = [
            PessoaResource::getPages()['edit']->getPage(),
            MatriculaResource::getPages()['boletim']->getPage(),
            MatriculaResource::getPages()['documentos']->getPage(),
            PreceptoriaResource::getPages()['index']->getPage(),
            PreceptoriaResource::getPages()['agendar']->getPage(),
        ];

        $faltando = array_values(array_filter($destinos, fn (string $classe) => ! $this->temAjuda($classe)));

        $this->assertSame([], $faltando, 'Destinos da sidebar do responsável sem botão de Ajuda');
    }

    public function test_todas_as_telas_do_portal_tem_botao_de_ajuda(): void
    {
        $this->assertSame([], $this->telasSemAjuda('portal'), 'Telas do portal sem botão de Ajuda');
    }
}
