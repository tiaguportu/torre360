<?php

namespace Tests\Feature;

use App\Support\HelpContent;
use App\Support\HelpEnricher;
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
        $this->assertStringContainsString('Legado</h3>', $view);

    }

    public function test_enriquecedor_adiciona_emoji_cores_resumo_e_callout_ao_html_legado(): void
    {
        $r = HelpEnricher::enriquecer('<p>Resumo da tela.</p><h3>O que você pode fazer?</h3><ul><li><strong>Novo:</strong> cria.</li><li><strong>Ações Individuais:</strong><ul><li><strong>Editar:</strong> altera.</li></ul></li></ul><h3>Estrutura</h3><ul><li>Texto</li></ul><p><small>Dica: use o filtro.</small></p>');

        $this->assertSame('Resumo da tela.', $r['resumo']);
        $this->assertStringContainsString('🎯 O que você pode fazer?', $r['html']);
        $this->assertStringContainsString('help-c0', $r['html']);
        $this->assertStringContainsString('help-c1', $r['html']);
        $this->assertStringContainsString('🆕', $r['html']);
        $this->assertStringContainsString('⚡', $r['html']);
        $this->assertStringContainsString('✏️', $r['html']);
        $this->assertStringContainsString('help-tip', $r['html']);
        $this->assertSame($r['html'], HelpEnricher::enriquecer($r['html'])['html'], 'deve ser idempotente');
    }

    public function test_blade_aplica_hero_e_emoji_em_conteudo_legado_sem_titulo(): void
    {
        $view = view('filament.components.help-content', [
            'content' => '<p>Gerencie cursos.</p><h3>Estrutura</h3><ul><li><strong>Listagem:</strong> veja tudo.</li></ul>',
        ])->render();

        $this->assertStringContainsString('help-hero', $view);
        $this->assertStringContainsString('Gerencie cursos.', $view);
        $this->assertStringContainsString('help-item-emoji', $view);
    }

    public function test_enriquecedor_trata_div_envolvente_h4_ol_e_conteudo_sem_titulo(): void
    {
        $div = HelpEnricher::enriquecer('<div class="space-y-3"><h3>Tipos de Ocorrências</h3><p>Cadastre tipos.</p></div>');
        $this->assertStringContainsString('help-sec', $div['html']);
        $this->assertStringNotContainsString('space-y-3', $div['html']);

        $passos = HelpEnricher::enriquecer('<p>Resumo.</p><h4>Como cadastrar:</h4><ol><li>Clique em Criar.</li><li>Salve.</li></ol>');
        $this->assertSame('Resumo.', $passos['resumo']);
        $this->assertStringContainsString('help-step-n">2<', $passos['html']);

        $solto = HelpEnricher::enriquecer('<p>Resumo.</p><ul><li>Item solto</li></ul>');
        $this->assertStringContainsString('📖 Sobre esta tela', $solto['html']);
        $this->assertStringContainsString('help-item', $solto['html']);

        $umParagrafo = HelpEnricher::enriquecer('<p>Só um parágrafo.</p>');
        $this->assertSame('Só um parágrafo.', $umParagrafo['resumo']);
    }
}
