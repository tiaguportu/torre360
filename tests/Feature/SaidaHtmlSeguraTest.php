<?php

namespace Tests\Feature;

use App\Enums\TipoEventoEscolar;
use App\Filament\Portal\Pages\EventosEscolares;
use App\Livewire\AssistantChatBubble;
use App\Models\EventoEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Conteúdo que não é do código (descrição de evento digitada por alguém, resposta da IA) não pode virar script.
 */
class SaidaHtmlSeguraTest extends TestCase
{
    use RefreshDatabase;

    public function test_descricao_de_evento_e_higienizada_no_portal_da_familia(): void
    {
        $user = User::create(['name' => 'Pai Teste', 'email' => 'pai@teste.com', 'password' => bcrypt('x')]);
        $responsavel = Pessoa::create(['nome' => 'Pai Teste', 'cpf' => '12312312399', 'user_id' => $user->id]);
        $aluno = Pessoa::create(['nome' => 'Filho Teste', 'cpf' => '45645645699', 'data_nascimento' => '2017-01-15']);
        $user->pessoas()->attach($responsavel);
        $responsavel->alunos()->attach($aluno);

        $periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $turma = Turma::create(['nome' => '1º Ano A', 'periodo_letivo_id' => $periodo->id]);
        Matricula::create(['pessoa_id' => $aluno->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);

        EventoEscolar::create([
            'titulo' => 'Festa da Primavera',
            'tipo' => TipoEventoEscolar::FestaComemorativa,
            'descricao' => '<p>Traga um prato <strong>doce</strong>.</p><script>document.title="hackeado"</script>'
                .'<img src="x" onerror="alert(1)"><a href="javascript:alert(2)">clique</a>',
            'data_inicio' => now()->addDays(10),
            'publico_alvo' => 'todos',
            'ativo' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(EventosEscolares::class)
            ->assertSeeHtml('<strong>doce</strong>')
            ->assertDontSeeHtml('<script>document.title')
            ->assertDontSeeHtml('onerror')
            ->assertDontSeeHtml('javascript:alert');
    }

    public function test_chat_do_assistente_so_cria_link_para_caminhos_do_sistema_e_http(): void
    {
        $chat = new AssistantChatBubble;

        // Perigosos: viram só texto.
        foreach ([
            '[clique](javascript:alert(1))',
            '[clique](JaVaScRiPt:alert(1))',
            '[clique]( javascript:alert(1))',
            '[clique](data:text/html;base64,PHNjcmlwdD4=)',
            '[clique](//atacante.test/phishing)',
            '[clique](/\atacante.test)',
            '[clique](vbscript:msgbox(1))',
        ] as $entrada) {
            $html = $chat->formatMessage($entrada);

            $this->assertStringNotContainsString('<a ', $html, $entrada);
            $this->assertStringNotContainsString('href', $html, $entrada);
            $this->assertStringContainsString('clique', $html, $entrada);
        }

        // Legítimos.
        $interno = $chat->formatMessage('[Matrículas](/admin/matriculas)');
        $this->assertStringContainsString('href="/admin/matriculas"', $interno);
        $this->assertStringContainsString('wire:navigate', $interno);

        $externo = $chat->formatMessage('[Site](https://exemplo.com/pagina)');
        $this->assertStringContainsString('href="https://exemplo.com/pagina"', $externo);
        $this->assertStringContainsString('rel="noopener noreferrer"', $externo);
        $this->assertStringContainsString('target="_blank"', $externo);

        $portal = $chat->formatMessage('[Portal](/portal/notas)');
        $this->assertStringContainsString('href="/portal/notas"', $portal);
        $this->assertStringNotContainsString('target="_blank"', $portal);
    }

    public function test_chat_continua_escapando_html_cru_da_resposta(): void
    {
        $html = (new AssistantChatBubble)->formatMessage('<script>alert(1)</script> **negrito**');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<strong>negrito</strong>', $html);
    }
}
