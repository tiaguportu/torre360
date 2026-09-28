<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Topo com Banner e Ação Principal -->
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="max-w-2xl">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/20 text-white backdrop-blur-md mb-2">
                    <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" /> Canal Direto Escola-Família
                </span>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Central de Atendimento e Mensagens</h2>
                <p class="mt-1 text-white/90 text-sm sm:text-base leading-relaxed">
                    Tire dúvidas pedagógicas, solicite 2ª via de boletos, fale com a secretaria ou envie mensagens diretamente para a coordenação sem precisar ligar ou comparecer presencialmente.
                </p>
            </div>
            <button 
                type="button" 
                wire:click="abrirNovoChamadoModal"
                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white text-blue-700 hover:bg-blue-50 font-bold text-sm shadow-md transition-all whitespace-nowrap">
                <x-heroicon-o-plus-circle class="w-5 h-5 text-blue-600" />
                Novo Chamado
            </button>
        </div>

        <!-- Lista de Chamados do Responsável -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">
                    Meus Atendimentos Recentes
                </h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Total: {{ $this->meusChamados->count() }} solicitação(ões)
                </span>
            </div>

            @if($this->meusChamados->isEmpty())
                <div class="text-center py-16 p-6">
                    <div class="inline-flex p-4 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-500 mb-3">
                        <x-heroicon-o-inbox-stack class="w-10 h-10" />
                    </div>
                    <h4 class="text-base font-bold text-gray-800 dark:text-gray-200">Você ainda não abriu nenhum chamado</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                        Precisa falar com a Secretaria, Financeiro ou Coordenação? Clique no botão "Novo Chamado" acima.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                            <tr>
                                <th class="px-5 py-3.5">Protocolo</th>
                                <th class="px-5 py-3.5">Setor</th>
                                <th class="px-5 py-3.5">Estudante</th>
                                <th class="px-5 py-3.5">Assunto</th>
                                <th class="px-5 py-3.5">Situação</th>
                                <th class="px-5 py-3.5">Data Abertura</th>
                                <th class="px-5 py-3.5 text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($this->meusChamados as $chamado)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-5 py-4 font-mono font-bold text-gray-900 dark:text-gray-100">
                                        {{ $chamado->protocolo }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                            {{ $chamado->setor?->nome }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-gray-700 dark:text-gray-300 font-medium">
                                        {{ $chamado->matricula?->pessoa?->nome ?? 'Geral / Família' }}
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-gray-900 dark:text-gray-100 max-w-xs truncate">
                                        {{ $chamado->assunto }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold
                                            @if($chamado->status->value === 'aberto') bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300
                                            @elseif($chamado->status->value === 'em_andamento') bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300
                                            @elseif($chamado->status->value === 'aguardando_solicitante') bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300
                                            @elseif($chamado->status->value === 'resolvido') bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endif">
                                            {{ $chamado->status->getLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                        {{ $chamado->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <button 
                                            type="button" 
                                            wire:click="verConversa({{ $chamado->id }})"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-primary-50 text-primary-700 hover:bg-primary-100 dark:bg-primary-950 dark:text-primary-300 transition-colors">
                                            <x-heroicon-o-chat-bubble-bottom-center-text class="w-4 h-4" />
                                            Ver Conversa
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Modal de Abertura de Novo Chamado -->
        @if($showNovoModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 relative animate-in fade-in duration-200">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <div class="p-2 rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-900/40">
                                <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5" />
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                Abrir Novo Chamado de Atendimento
                            </h3>
                        </div>
                        <button wire:click="$set('showNovoModal', false)" class="text-gray-400 hover:text-gray-500">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    <form wire:submit.prevent="criarChamado" class="mt-4 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Setor -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                    Setor de Destino *
                                </label>
                                <select 
                                    wire:model="novoSetorId" 
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">Selecione o setor...</option>
                                    @foreach($this->setores as $setor)
                                        <option value="{{ $setor->id }}">{{ $setor->nome }}</option>
                                    @endforeach
                                </select>
                                @error('novoSetorId') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Aluno Vinculado -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                    Estudante Vinculado
                                </label>
                                <select 
                                    wire:model="novoMatriculaId" 
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">Geral / Toda a Família</option>
                                    @foreach($this->matriculasAcessiveis as $m)
                                        <option value="{{ $m->id }}">{{ $m->pessoa?->nome }} ({{ $m->turma?->nome }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Assunto -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Assunto do Chamado *
                            </label>
                            <input 
                                type="text" 
                                wire:model="novoAssunto" 
                                placeholder="Ex: Solicitação de histórico ou ajuste em boleto"
                                class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500" />
                            @error('novoAssunto') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Mensagem Inicial -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Detalhes da Solicitação *
                            </label>
                            <textarea 
                                wire:model="novaMensagemInicial" 
                                rows="4" 
                                placeholder="Explique com detalhes sua dúvida ou requerimento..."
                                class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500"></textarea>
                            @error('novaMensagemInicial') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Anexo Opcional -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                Anexar Arquivo ou Comprovante (Opcional)
                            </label>
                            <input 
                                type="file" 
                                wire:model="novoAnexo" 
                                class="text-xs text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100" />
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <button 
                                type="button" 
                                wire:click="$set('showNovoModal', false)"
                                class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                Cancelar
                            </button>
                            <button 
                                type="submit" 
                                class="px-5 py-2.5 rounded-xl text-sm font-bold bg-primary-600 hover:bg-primary-700 text-white shadow-md transition-all">
                                Enviar Chamado
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Modal de Visualização da Conversa / Linha do Tempo -->
        @if($showConversaModal && $this->chamadoAtual)
            @php $chamado = $this->chamadoAtual; @endphp
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-gray-200 dark:border-gray-700 relative animate-in fade-in duration-200 flex flex-col max-h-[90vh]">
                    <!-- Cabeçalho do Chamado -->
                    <div class="flex items-start justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-sm bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded text-gray-800 dark:text-gray-200">
                                    {{ $chamado->protocolo }}
                                </span>
                                <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200">
                                    {{ $chamado->setor?->nome }}
                                </span>
                                <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                                    {{ $chamado->status->getLabel() }}
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1">
                                {{ $chamado->assunto }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Aberto em {{ $chamado->created_at->format('d/m/Y \à\s H:i') }}
                                @if($chamado->matricula)
                                    — Aluno: <strong>{{ $chamado->matricula->pessoa?->nome }}</strong>
                                @endif
                            </p>
                        </div>
                        <button wire:click="$set('showConversaModal', false)" class="text-gray-400 hover:text-gray-500">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    <!-- Linha do Tempo de Mensagens (Rolável) -->
                    <div class="my-4 space-y-4 overflow-y-auto flex-1 pr-2 max-h-[350px]">
                        @foreach($chamado->mensagens as $msg)
                            @php
                                $isEscola = (bool) $msg->user_id;
                            @endphp
                            <div class="flex {{ $isEscola ? 'justify-start' : 'justify-end' }}">
                                <div class="max-w-[85%] rounded-2xl p-4 {{ $isEscola ? 'bg-blue-50 dark:bg-blue-950/60 border border-blue-100 dark:border-blue-900/60 text-gray-800 dark:text-gray-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">
                                    <div class="flex items-center justify-between gap-3 text-xs mb-1.5 opacity-75">
                                        <span class="font-bold flex items-center gap-1">
                                            @if($isEscola)
                                                <x-heroicon-o-building-library class="w-3.5 h-3.5 text-blue-600" />
                                                {{ $msg->user?->name }} (Equipe Escolar)
                                            @else
                                                <x-heroicon-o-user class="w-3.5 h-3.5" />
                                                Você (Família)
                                            @endif
                                        </span>
                                        <span>{{ $msg->created_at->format('d/m/Y H:i') }}</span>
                                    </div>

                                    <div class="text-sm leading-relaxed whitespace-pre-wrap">
                                        {{ $msg->mensagem }}
                                    </div>

                                    @if($msg->anexo_path)
                                        <div class="mt-3 pt-2 border-t border-black/10 dark:border-white/10">
                                            <a 
                                                href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($msg->anexo_path) }}" 
                                                target="_blank" 
                                                class="inline-flex items-center gap-1.5 text-xs font-bold text-primary-600 dark:text-primary-400 hover:underline">
                                                <x-heroicon-o-paper-clip class="w-4 h-4" />
                                                Ver Anexo Enviado
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Envio de Réplica / Nova Mensagem -->
                    @if($chamado->status->value !== 'fechado')
                        <div class="pt-4 border-t border-gray-100 dark:border-gray-700 space-y-3">
                            <textarea 
                                wire:model="respostaTexto" 
                                rows="2" 
                                placeholder="Escreva sua resposta para a escola..." 
                                class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:ring-primary-500 focus:border-primary-500"></textarea>

                            <div class="flex items-center justify-between gap-3">
                                <input 
                                    type="file" 
                                    wire:model="respostaAnexo" 
                                    class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />

                                <button 
                                    type="button" 
                                    wire:click="enviarResposta"
                                    class="px-5 py-2 rounded-xl text-sm font-bold bg-primary-600 hover:bg-primary-700 text-white shadow transition-colors flex items-center gap-1.5">
                                    <x-heroicon-o-paper-airplane class="w-4 h-4" />
                                    Enviar Resposta
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Avaliação de Atendimento (quando resolvido) -->
                    @if(in_array($chamado->status->value, ['resolvido', 'fechado']))
                        <div class="mt-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-amber-900 dark:text-amber-200 mb-2 flex items-center gap-1.5">
                                <x-heroicon-o-star class="w-4 h-4" /> Avaliação do Atendimento
                            </h4>

                            @if($chamado->avaliacao_nota)
                                <div class="flex items-center gap-1 text-amber-500 mb-1">
                                    @for($i = 1; $i <= 5; $i++)
                                        <x-heroicon-s-star class="w-5 h-5 {{ $i <= $chamado->avaliacao_nota ? 'text-amber-500' : 'text-gray-300' }}" />
                                    @endfor
                                    <span class="text-xs font-bold ml-2 text-gray-700 dark:text-gray-300">
                                        Nota: {{ $chamado->avaliacao_nota }}/5
                                    </span>
                                </div>
                                @if($chamado->avaliacao_comentario)
                                    <p class="text-xs text-gray-600 dark:text-gray-400 italic">"{{ $chamado->avaliacao_comentario }}"</p>
                                @endif
                            @else
                                <p class="text-xs text-amber-800 dark:text-amber-300 mb-2">
                                    Este atendimento foi concluído. Como você avalia a resposta da escola?
                                </p>
                                <div class="flex items-center gap-2 mb-2">
                                    @for($i = 1; $i <= 5; $i++)
                                        <button 
                                            type="button" 
                                            wire:click="$set('notaAvaliacao', {{ $i }})"
                                            class="p-1 rounded hover:scale-110 transition-transform">
                                            <x-heroicon-s-star class="w-6 h-6 {{ $notaAvaliacao && $i <= $notaAvaliacao ? 'text-amber-500' : 'text-gray-300' }}" />
                                        </button>
                                    @endfor
                                </div>
                                <div class="flex gap-2">
                                    <input 
                                        type="text" 
                                        wire:model="comentarioAvaliacao" 
                                        placeholder="Comentário opcional sobre o atendimento..." 
                                        class="flex-1 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-xs" />
                                    <button 
                                        type="button" 
                                        wire:click="enviarAvaliacao"
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white">
                                        Enviar Avaliação
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
