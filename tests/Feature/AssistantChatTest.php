<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\AssistantChatBubble;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa se o componente do chat flutuante carrega corretamente na montagem inicial.
     */
    public function test_componente_monta_com_mensagem_inicial_de_boas_vindas(): void
    {
        Livewire::test(AssistantChatBubble::class)
            ->assertSet('isOpen', false)
            ->assertCount('messages', 1)
            ->assertSee('Olá! Sou o assistente inteligente do Torre360.');
    }

    /**
     * Testa a alternância de visibilidade do painel de chat.
     */
    public function test_alternar_visibilidade_do_chat_persiste_na_sessao(): void
    {
        Livewire::test(AssistantChatBubble::class)
            ->assertSet('isOpen', false)
            ->call('toggleChat')
            ->assertSet('isOpen', true);

        $this->assertTrue(session()->get('assistant_chat_open'));

        Livewire::test(AssistantChatBubble::class)
            ->assertSet('isOpen', true)
            ->call('toggleChat')
            ->assertSet('isOpen', false);

        $this->assertFalse(session()->get('assistant_chat_open'));
    }

    /**
     * Testa a limpeza do histórico de mensagens.
     */
    public function test_limpar_historico_de_conversas(): void
    {
        session()->put('assistant_chat_history', [
            ['role' => 'user', 'content' => 'Olá', 'time' => '10:00'],
            ['role' => 'assistant', 'content' => 'Como posso ajudar?', 'time' => '10:00'],
        ]);

        Livewire::test(AssistantChatBubble::class)
            ->assertCount('messages', 2)
            ->call('clearChat')
            ->assertCount('messages', 1)
            ->assertSee('Histórico limpo. Como posso te ajudar hoje?');

        $history = session()->get('assistant_chat_history');
        $this->assertCount(1, $history);
        $this->assertEquals('assistant', $history[0]['role']);
    }

    /**
     * Testa o fluxo de envio de pergunta do usuário e recebimento de resposta mockada da IA.
     */
    public function test_envio_de_mensagem_chama_servico_de_ia_com_url_atual_e_grava_historico(): void
    {
        $this->mock(GeminiAgentService::class, function ($mock): void {
            $mock->shouldReceive('ask')
                ->once()
                ->with(
                    'como cadastrar uma matricula?',
                    [
                        ['role' => 'assistant', 'content' => 'Olá! Sou o assistente inteligente do Torre360. Como posso te ajudar com a plataforma hoje?'],
                    ],
                    'http://localhost/admin/matriculas/create'
                )
                ->andReturn('Para cadastrar uma matrícula, acesse a tela de [Matrículas](/admin/matriculas) e clique em Criar.');
        });

        Livewire::test(AssistantChatBubble::class)
            ->set('userInput', 'como cadastrar uma matricula?')
            ->call('sendMessage', 'http://localhost/admin/matriculas/create')
            ->assertSet('userInput', '')
            ->assertCount('messages', 3) // Mensagem Inicial + Usuário + Resposta IA
            ->assertSee('Para cadastrar uma matrícula, acesse a tela de');

        $history = session()->get('assistant_chat_history');
        $this->assertCount(3, $history);
        $this->assertEquals('user', $history[1]['role']);
        $this->assertEquals('como cadastrar uma matricula?', $history[1]['content']);
        $this->assertEquals('assistant', $history[2]['role']);
    }

    /**
     * Testa se mensagens vazias não são enviadas.
     */
    public function test_nao_envia_mensagens_em_branco(): void
    {
        $this->mock(GeminiAgentService::class, function ($mock): void {
            $mock->shouldNotReceive('ask');
        });

        Livewire::test(AssistantChatBubble::class)
            ->set('userInput', '   ')
            ->call('sendMessage', 'http://localhost/admin')
            ->assertCount('messages', 1); // Mantém apenas a de boas-vindas
    }

    /**
     * Testa se o componente do chat flutuante é renderizado no painel administrativo para usuários autorizados.
     */
    public function test_chat_bubble_is_rendered_in_admin_panel_for_authorized_users(): void
    {
        // Cria a role super_admin se não existir
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        
        $user = \App\Models\User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get('/admin')
            ->assertSeeLivewire(AssistantChatBubble::class);
    }
}

