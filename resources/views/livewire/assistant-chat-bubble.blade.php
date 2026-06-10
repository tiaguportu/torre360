<div x-data="{ 
    open: @entangle('isOpen'),
    scrollToBottom() {
        this.$nextTick(() => {
            const container = this.$refs.chatContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    }
}" 
x-init="
    scrollToBottom();
    $watch('open', value => { if (value) scrollToBottom(); });
"
@scroll-to-bottom.window="scrollToBottom()"
class="fixed bottom-6 right-6 z-[9999] font-sans"
>
    <!-- Botão Flutuante (Bolha de Chat) -->
    <button 
        @click="open = !open" 
        class="flex items-center justify-center w-14 h-14 rounded-full shadow-2xl transition duration-300 transform hover:scale-105 active:scale-95 focus:outline-none"
        style="background-color: #243468; color: white; border: none; cursor: pointer;"
        title="Assistente de IA"
    >
        <!-- Ícone Inteligente/IA (Sparkles + Chat) -->
        <span x-show="!open" class="transition-all duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-7 h-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
        </span>
        <span x-show="open" class="transition-all duration-300" x-cloak>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-7 h-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </span>
    </button>

    <!-- Janela de Chat Flutuante -->
    <div 
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-8 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-8 scale-95"
        class="absolute bottom-16 right-0 w-[380px] max-w-[calc(100vw-2rem)] h-[550px] max-h-[calc(100vh-6rem)] bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden"
        x-cloak
    >
        <!-- Cabeçalho do Chat -->
        <div class="px-4 py-3 bg-[#243468] text-white flex items-center justify-between shrink-0 shadow-md">
            <div class="flex items-center gap-3">
                <div class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </div>
                <div>
                    <h3 class="font-bold text-sm leading-tight">Assistente Torre360</h3>
                    <p class="text-[10px] text-gray-200">Baseado no Manual do Usuário</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <!-- Botão de Limpar Histórico -->
                <button 
                    wire:click="clearChat" 
                    class="p-1.5 hover:bg-white/10 rounded-lg transition" 
                    title="Limpar Histórico"
                    wire:loading.attr="disabled"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                </button>
                <!-- Botão de Minimizar -->
                <button @click="open = false" class="p-1.5 hover:bg-white/10 rounded-lg transition" title="Minimizar">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Área de Conversação -->
        <div 
            x-ref="chatContainer"
            class="flex-1 p-4 overflow-y-auto space-y-4 bg-gray-50/50 dark:bg-gray-950/20"
        >
            @foreach($messages as $index => $message)
                <div class="flex gap-3 {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }} items-start">
                    <!-- Avatar da IA -->
                    @if($message['role'] === 'assistant')
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-white dark:bg-gray-800 text-gray-500 border border-gray-200 dark:border-gray-700 shrink-0 shadow-sm">
                            <!-- Ícone da Estrela/IA -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-[#243468]">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21L8.188 15.904L3 15L8.188 14.096L9 9L9.813 14.096L15 15L9.813 15.904Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.071 4.929a10 10 0 00-14.142 0M19.071 4.929a10 10 0 010 14.142M4.929 19.071a10 10 0 0114.142 0M4.929 19.071a10 10 0 010-14.142" />
                            </svg>
                        </div>
                    @endif

                    <!-- Balão de Mensagem -->
                    <div class="flex flex-col gap-1 max-w-[75%]">
                        <div class="px-4 py-3 rounded-2xl border text-sm shadow-sm
                            {{ $message['role'] === 'user' 
                                ? 'bg-[#243468] text-white rounded-tr-none border-[#243468]/20' 
                                : 'bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 rounded-tl-none border-gray-200/60 dark:border-gray-700/60'
                            }}"
                        >
                            @if($message['role'] === 'user')
                                {{ $message['content'] }}
                            @else
                                <div class="prose prose-sm dark:prose-invert max-w-none break-words leading-relaxed space-y-1">
                                    {!! $this->formatMessage($message['content']) !!}
                                </div>
                            @endif
                        </div>
                        <span class="text-[9px] text-gray-400 self-end px-1">{{ $message['time'] }}</span>
                    </div>
                </div>
            @endforeach

            <!-- Feedback visual de "IA pensando..." -->
            <div wire:loading wire:target="sendMessage" class="flex gap-3 justify-start items-start">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-white dark:bg-gray-800 text-gray-500 border border-gray-200 dark:border-gray-700 shrink-0 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 animate-pulse text-[#243468]">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21L8.188 15.904L3 15L8.188 14.096L9 9L9.813 14.096L15 15L9.813 15.904Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.071 4.929a10 10 0 00-14.142 0M19.071 4.929a10 10 0 010 14.142M4.929 19.071a10 10 0 0114.142 0M4.929 19.071a10 10 0 010-14.142" />
                    </svg>
                </div>
                <div class="flex flex-col gap-1 max-w-[75%]">
                    <div class="px-4 py-3 bg-white dark:bg-gray-800 rounded-2xl rounded-tl-none border border-gray-200/60 dark:border-gray-700/60">
                        <div class="flex items-center gap-1.5 py-1">
                            <span class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                            <span class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                            <span class="w-2 h-2 bg-gray-400 dark:bg-gray-500 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulário de Input (Fixo embaixo) -->
        <div class="p-3 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 shrink-0">
            <form 
                wire:submit.prevent="sendMessage" 
                class="flex gap-2 items-center"
            >
                <input 
                    type="text" 
                    wire:model="userInput" 
                    placeholder="Digite sua dúvida..." 
                    class="flex-1 px-4 py-2.5 bg-gray-50 dark:bg-gray-850 border border-gray-200 dark:border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#243468] text-sm dark:text-white dark:bg-gray-800 placeholder-gray-400"
                    wire:loading.attr="disabled"
                    wire:target="sendMessage"
                >
                <button 
                    type="submit" 
                    class="p-2.5 rounded-xl transition flex items-center justify-center shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#243468]"
                    style="background-color: #243468; color: white;"
                    wire:loading.attr="disabled"
                    wire:target="sendMessage"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>
