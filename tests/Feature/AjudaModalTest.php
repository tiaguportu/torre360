<?php

namespace Tests\Feature;

use App\Support\HelpContent;
use Tests\TestCase;

class AjudaModalTest extends TestCase
{
    public function test_builder_gera_secoes_passos_e_callouts_com_emoji(): void
    {
        $html = HelpContent::make('🎓', 'Cursos', 'Resumo')
            ->secao('🎯 Ações', [['🆕', 'Novo', 'Cria'], null, false, ['Só título', 'texto']])
            ->passos('🚀 Passos', ['Um', 'Dois'])
            ->dica('Dica')
            ->alerta('Cuidado')
            ->render();

        $this->assertStringContainsString('🎯 Ações', $html);
        $this->assertStringContainsString('🆕', $html);
        $this->assertStringContainsString('🔹', $html);
        $this->assertStringContainsString('help-step-n">2<', $html);
        $this->assertStringContainsString('help-tip', $html);
        $this->assertStringContainsString('help-warn', $html);
    }

    public function test_builder_escapa_html_e_ignora_secao_vazia(): void
    {
        $html = HelpContent::make('❓', 'T')->secao('Vazia', [null])->secao('X', [['<script>', 'a']])->render();

        $this->assertStringNotContainsString('Vazia', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_blade_renderiza_hero_e_conteudo_legado(): void
    {
        $view = view('filament.components.help-content', [
            'content' => '<h3>Legado</h3>',
            'icone' => '📚',
            'titulo' => 'Título',
            'resumo' => 'Resumo',
        ])->render();

        $this->assertStringContainsString('help-hero', $view);
        $this->assertStringContainsString('📚', $view);
        $this->assertStringContainsString('<h3>Legado</h3>', $view);

        $legado = view('filament.components.help-content', ['content' => '<p>x</p>'])->render();
        $this->assertStringNotContainsString('help-hero"', $legado);
    }
}
