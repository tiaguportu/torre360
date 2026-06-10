<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\GeminiAgentService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AssistantChatBubble extends Component
{
    public bool $isOpen = false;

    /** @var array<int, array{role: string, content: string, time: string}> */
    public array $messages = [];

    public string $userInput = '';

    public string $currentUrl = '';

    /**
     * Inicializa o estado do componente carregando o histórico e o estado da sessão.
     */
    public function mount(): void
    {
        $this->messages = session()->get('assistant_chat_history', [
            [
                'role' => 'assistant',
                'content' => 'Olá! Sou o assistente inteligente do Torre360. Como posso te ajudar com a plataforma hoje?',
                'time' => now()->format('H:i'),
            ],
        ]);

        $this->isOpen = (bool) session()->get('assistant_chat_open', false);
    }

    /**
     * Alterna o estado de visibilidade da janela do chat e salva na sessão.
     */
    public function toggleChat(): void
    {
        $this->isOpen = ! $this->isOpen;
        session()->put('assistant_chat_open', $this->isOpen);

        if ($this->isOpen) {
            $this->dispatch('scroll-to-bottom');
        }
    }

    /**
     * Limpa o histórico de conversa na sessão.
     */
    public function clearChat(): void
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'content' => 'Histórico limpo. Como posso te ajudar hoje?',
                'time' => now()->format('H:i'),
            ],
        ];

        session()->put('assistant_chat_history', $this->messages);
        $this->userInput = '';

        $this->dispatch('scroll-to-bottom');
    }

    /**
     * Envia a mensagem do usuário para o serviço de IA e adiciona a resposta no histórico.
     */
    public function sendMessage(string $url): void
    {
        $this->userInput = trim($this->userInput);

        if (empty($this->userInput)) {
            return;
        }

        $messageText = $this->userInput;
        $this->userInput = '';
        $this->currentUrl = $url;

        // Adiciona a mensagem do usuário ao histórico local e da sessão
        $this->messages[] = [
            'role' => 'user',
            'content' => $messageText,
            'time' => now()->format('H:i'),
        ];
        session()->put('assistant_chat_history', $this->messages);

        // Faz o scroll imediatamente para a mensagem do usuário recém enviada
        $this->dispatch('scroll-to-bottom');

        // Instancia o serviço de IA
        $service = app(GeminiAgentService::class);

        // Prepara o histórico para o formato da API (removendo a mensagem que acabou de ser inserida)
        $historyForApi = array_map(function (array $msg): array {
            return [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }, array_slice($this->messages, 0, -1));

        // Obtém a resposta da IA
        $response = $service->ask($messageText, $historyForApi, $this->currentUrl);

        // Adiciona a resposta ao histórico local e à sessão
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $response,
            'time' => now()->format('H:i'),
        ];
        session()->put('assistant_chat_history', $this->messages);

        // Executa o scroll para o final após renderizar a resposta da IA
        $this->dispatch('scroll-to-bottom');
    }

    /**
     * Converte Markdown básico (negrito, itálico, links) em HTML seguro.
     * Insere wire:navigate nos links do painel para manter navegação SPA sem recarregar a página.
     */
    public function formatMessage(string $text): string
    {
        $escaped = e($text);

        // Converte quebras de linha em <br>
        $escaped = nl2br($escaped);

        // Converte **negrito** para <strong>
        $escaped = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $escaped);

        // Converte *itálico* para <em>
        $escaped = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $escaped);

        // Converte links markdown [Texto](URL) e injeta o wire:navigate do Livewire para manter a navegação instantânea no SPA
        $escaped = preg_replace_callback(
            '/\[(.*?)\]\((.*?)\)/',
            function (array $matches): string {
                $text = $matches[1];
                $url = html_entity_decode($matches[2]);

                // Se for um link relativo do painel admin, adicionamos wire:navigate
                $navigate = str_starts_with($url, '/admin') ? 'wire:navigate' : '';

                return sprintf(
                    '<a href="%s" %s class="underline font-semibold hover:opacity-80 transition" style="color: #243468; text-decoration: underline;">%s</a>',
                    e($url),
                    $navigate,
                    $text
                );
            },
            $escaped
        );

        return $escaped;
    }

    /**
     * Renderiza a view do componente.
     *
     * @return View
     */
    public function render()
    {
        return view('livewire.assistant-chat-bubble');
    }
}
