@php
    /** @var \App\Filament\Resources\Interessados\RelationManagers\TimelineRelationManager $livewire */
    $metricas = $livewire->metricas;
    $eventos = $livewire->timeline;
    $tiposContato = $livewire->tiposContato;
    $telefonePessoa = preg_replace('/\D/', '', $metricas['telefone_contato'] ?? '');
    if (!empty($telefonePessoa) && strlen($telefonePessoa) <= 11 && !str_starts_with($telefonePessoa, '55')) {
        $telefoneWhatsapp = '55' . $telefonePessoa;
    } else {
        $telefoneWhatsapp = $telefonePessoa;
    }
@endphp

<div class="space-y-6 text-sm text-gray-700 dark:text-gray-200">
    
    <!-- ========================================== -->
    <!-- 1. BARRA SUPERIOR DE INDICADORES 360°      -->
    <!-- ========================================== -->
    <div class="rounded-2xl border border-gray-200/80 dark:border-gray-800 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md p-4 sm:p-5 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            
            <!-- Resumo do Lead e Temperatura -->
            <div class="flex items-center gap-3.5">
                <div class="relative flex items-center justify-center w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md shadow-indigo-500/20 font-black text-lg">
                    <span>360°</span>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Linha do Tempo Omnichannel
                        </h3>
                        
                        <!-- Badge Temperatura -->
                        @php
                            $tempClasses = match(mb_strtolower($metricas['temperatura'])) {
                                'quente' => 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                                'frio' => 'bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300 border-sky-200 dark:border-sky-800',
                                default => 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                            };
                            $tempIcon = match(mb_strtolower($metricas['temperatura'])) {
                                'quente' => '🔥',
                                'frio' => '❄️',
                                default => '🌤️',
                            };
                        @endphp
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $tempClasses }}">
                            <span>{{ $tempIcon }}</span> {{ ucfirst($metricas['temperatura']) }}
                        </span>

                        <!-- Lead Score -->
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                            ⭐ Score: {{ $metricas['lead_score'] }} pts
                        </span>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Consultor: <strong class="text-gray-700 dark:text-gray-300">{{ $metricas['consultor_nome'] }}</strong> • Status: <span class="font-medium">{{ $metricas['status_nome'] }}</span>
                    </p>
                </div>
            </div>

            <!-- Chips de Métricas e Ações Rápidas -->
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <!-- Próximo Retorno / Alerta Atraso -->
                @if($metricas['proximo_contato_em'])
                    <div class="px-3 py-1.5 rounded-xl border text-xs font-medium flex items-center gap-2 {{ $metricas['esta_em_atraso'] ? 'bg-rose-50 dark:bg-rose-950/50 border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300' }}">
                        <span class="flex h-2 w-2 rounded-full {{ $metricas['esta_em_atraso'] ? 'bg-rose-600 animate-ping' : 'bg-emerald-500' }}"></span>
                        <span>
                            Próximo Contato:
                            <strong>{{ $metricas['proximo_contato_em']->format('d/m/Y H:i') }}</strong>
                            @if($metricas['esta_em_atraso'])
                                <span class="font-bold underline ml-1">({{ $metricas['dias_atraso'] }}d em atraso)</span>
                            @endif
                        </span>
                    </div>
                @endif

                <!-- Total de Eventos -->
                <div class="px-3 py-1.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-300">
                    Total: <strong class="text-gray-900 dark:text-white">{{ $metricas['total_interacoes'] }}</strong> interações
                </div>

                <!-- Botão de Ajuda do Header -->
                {{ ($livewire->ajudaAction)(['class' => 'cursor-pointer']) }}
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. BARRA DE REGISTRO RÁPIDO DE INTERAÇÃO   -->
    <!-- ========================================== -->
    <div class="rounded-2xl border border-indigo-200 dark:border-indigo-900/60 bg-gradient-to-br from-indigo-50/70 via-white to-purple-50/50 dark:from-indigo-950/20 dark:via-gray-900 dark:to-purple-950/20 p-4 sm:p-5 shadow-xs transition-all">
        
        <div class="flex items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2">
                <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-600 text-white text-xs font-bold">
                    ⚡
                </span>
                <h4 class="font-bold text-sm text-gray-900 dark:text-white">
                    Registrar Nova Interação Rápida
                </h4>
            </div>

            <button 
                type="button" 
                wire:click="toggleFormularioRapido"
                class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer flex items-center gap-1"
            >
                @if($livewire->mostrarFormularioRapido)
                    <span>Recolher</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                @else
                    <span>Expandir Formulário</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                @endif
            </button>
        </div>

        @if($livewire->mostrarFormularioRapido)
            <form wire:submit="registrarContatoRapido" class="space-y-3.5 mt-2">
                
                <!-- Seleção Rápida de Canal (Pills) -->
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        Canal da Interação:
                    </label>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($tiposContato as $tipo)
                            @php
                                $selecionado = (int)$livewire->novoTipoContatoId === (int)$tipo->id;
                                $slug = mb_strtolower($tipo->nome);
                                $corBtn = match(true) {
                                    str_contains($slug, 'whatsapp') => $selecionado ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50',
                                    str_contains($slug, 'liga') || str_contains($slug, 'telef') => $selecionado ? 'bg-sky-600 text-white border-sky-600 shadow-xs' : 'bg-white dark:bg-gray-800 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800 hover:bg-sky-50',
                                    str_contains($slug, 'mail') => $selecionado ? 'bg-purple-600 text-white border-purple-600 shadow-xs' : 'bg-white dark:bg-gray-800 text-purple-700 dark:text-purple-400 border-purple-200 dark:border-purple-800 hover:bg-purple-50',
                                    str_contains($slug, 'presen') => $selecionado ? 'bg-amber-600 text-white border-amber-600 shadow-xs' : 'bg-white dark:bg-gray-800 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800 hover:bg-amber-50',
                                    default => $selecionado ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white dark:bg-gray-800 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800 hover:bg-indigo-50',
                                };
                            @endphp
                            <button 
                                type="button" 
                                wire:click="$set('novoTipoContatoId', {{ $tipo->id }})"
                                class="px-3 py-1.5 rounded-xl border text-xs font-semibold cursor-pointer transition-all {{ $corBtn }}"
                            >
                                {{ $tipo->nome }}
                            </button>
                        @endforeach
                    </div>
                    @error('novoTipoContatoId') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Campo de Resumo do Atendimento -->
                <div>
                    <label for="novoRelato" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        O que foi conversado ou acordado com a família?
                    </label>
                    <textarea 
                        id="novoRelato"
                        wire:model="novoRelato"
                        rows="2"
                        placeholder="Ex: Família adorou os laboratórios de robótica e solicitou simulação da anuidade do 7º ano. Combinado retorno na próxima terça..."
                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-hidden transition-colors"
                        required
                    ></textarea>
                    @error('novoRelato') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Parâmetros Adicionais (Resultado, Próximo Contato, Duração) -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- Resultado -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            Resultado:
                        </label>
                        <select 
                            wire:model="novoResultado"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-hidden"
                        >
                            <option value="retornar">Retornar depois</option>
                            <option value="agendou_visita">Agendou Visita</option>
                            <option value="matriculou">Efetuou Matrícula</option>
                            <option value="sem_interesse">Sem Interesse</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>

                    <!-- Agendar Próximo Contato -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            Agendar Próximo Retorno:
                        </label>
                        <input 
                            type="datetime-local" 
                            wire:model="novaDataProximoContato"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-hidden"
                        />
                    </div>

                    <!-- Duração em Minutos -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            Duração (minutos):
                        </label>
                        <input 
                            type="number" 
                            min="1"
                            wire:model="novaDuracaoMinutos"
                            placeholder="Ex: 15"
                            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-hidden"
                        />
                    </div>
                </div>

                <!-- Botão de Registro -->
                <div class="flex items-center justify-end gap-2 pt-1">
                    <button 
                        type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 cursor-pointer shadow-sm transition-colors disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="registrarContatoRapido">⚡ Gravar Interação & Recalcular Score</span>
                        <span wire:loading wire:target="registrarContatoRapido" class="inline-flex items-center gap-1">
                            <svg class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                            Gravando...
                        </span>
                    </button>
                </div>
            </form>
        @endif
    </div>

    <!-- ========================================== -->
    <!-- 3. BARRA DE FILTROS E BUSCA TEMPO REAL     -->
    <!-- ========================================== -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pt-2">
        
        <!-- Categorias (Pills) -->
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
            @php
                $categorias = [
                    'todos' => ['label' => 'Todos os Eventos', 'icone' => '📋'],
                    'contatos' => ['label' => 'Contatos & Mensagens', 'icone' => '💬'],
                    'visitas' => ['label' => 'Visitas & NPS', 'icone' => '🏫'],
                    'documentos' => ['label' => 'Documentos & IA', 'icone' => '📑'],
                    'etapas' => ['label' => 'Etapas & Funil', 'icone' => '🔄'],
                ];
            @endphp

            @foreach($categorias as $catChave => $catInfo)
                @php
                    $ativa = $livewire->filtroCategoria === $catChave;
                @endphp
                <button 
                    type="button" 
                    wire:click="filtrar('{{ $catChave }}')"
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer transition-all border {{ $ativa ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700' }}"
                >
                    <span class="mr-1">{{ $catInfo['icone'] }}</span>
                    {{ $catInfo['label'] }}
                </button>
            @endforeach
        </div>

        <!-- Campo de Busca Textual -->
        <div class="relative w-full md:w-72">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="termoBusca"
                placeholder="Buscar palavra ou relato..."
                class="w-full pl-9 pr-8 py-1.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-hidden transition-colors"
            />
            @if(!empty($livewire->termoBusca))
                <button 
                    type="button" 
                    wire:click="$set('termoBusca', '')"
                    class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            @endif
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 4. FEED VISUAL DA LINHA DO TEMPO 360°      -->
    <!-- ========================================== -->
    <div class="relative pt-3 pb-6">
        
        @if($eventos->isEmpty())
            <!-- Estado Vazio (Empty State) -->
            <div class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 bg-white/50 dark:bg-gray-900/50 p-8 sm:p-12 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 text-2xl mb-3">
                    🔍
                </div>
                <h4 class="font-bold text-base text-gray-900 dark:text-white mb-1">
                    Nenhum evento encontrado
                </h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-4">
                    Não encontramos nenhuma interação para a categoria ou termo de busca selecionado. Tente alterar o filtro ou registre o primeiro contato acima!
                </p>
                <button 
                    type="button" 
                    wire:click="limparFiltros"
                    class="px-3.5 py-1.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                >
                    Ver Todos os Eventos
                </button>
            </div>
        @else
            <!-- Linha Conectora Vertical -->
            <div class="absolute left-6 sm:left-7 top-6 bottom-4 w-0.5 bg-gray-200 dark:bg-gray-800"></div>

            <div class="space-y-6">
                @foreach($eventos as $evento)
                    <div class="relative flex items-start gap-4 sm:gap-5 group">
                        
                        <!-- Nó da Timeline (Ícone Flutuante) -->
                        <div class="relative z-10 flex items-center justify-center w-12 h-12 rounded-2xl border shadow-xs shrink-0 ring-4 ring-gray-50 dark:ring-gray-950 transition-transform group-hover:scale-105 {{ $evento['bg_icone'] }} {{ $evento['cor_icone'] }}">
                            @if($evento['icone'] === 'heroicon-o-chat-bubble-left-ellipsis')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            @elseif($evento['icone'] === 'heroicon-o-phone')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            @elseif($evento['icone'] === 'heroicon-o-academic-cap')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/></svg>
                            @elseif($evento['icone'] === 'heroicon-o-document-check')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($evento['icone'] === 'heroicon-o-envelope')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            @elseif($evento['icone'] === 'heroicon-o-fire')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                            @elseif($evento['icone'] === 'heroicon-o-x-circle')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @else
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            @endif
                        </div>

                        <!-- Card de Conteúdo do Evento -->
                        <div class="flex-1 rounded-2xl border border-gray-200/90 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 sm:p-5 shadow-xs hover:border-gray-300 dark:hover:border-gray-700 transition-all">
                            
                            <!-- Header do Card -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 sm:gap-2 mb-2.5">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h5 class="text-sm font-bold text-gray-900 dark:text-white">
                                            {{ $evento['titulo'] }}
                                        </h5>

                                        @if(!empty($evento['badge']))
                                            @php
                                                $badgeCor = $evento['badge_cor'] ?? 'gray';
                                                $bClasses = match($badgeCor) {
                                                    'emerald', 'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                                    'amber', 'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                                                    'rose', 'danger' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                                                    'info', 'blue' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300 border-sky-200 dark:border-sky-800',
                                                    default => 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[11px] font-semibold border {{ $bClasses }}">
                                                {{ $evento['badge'] }}
                                            </span>
                                        @endif
                                    </div>

                                    @if(!empty($evento['subtitulo']))
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ $evento['subtitulo'] }}
                                        </p>
                                    @endif
                                </div>

                                <div class="text-left sm:text-right shrink-0">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">
                                        {{ $evento['data_relativa'] }}
                                    </span>
                                    <span class="text-[11px] text-gray-400 block" title="{{ $evento['data_formatada'] }}">
                                        {{ $evento['data_formatada'] }}
                                    </span>
                                </div>
                            </div>

                            <!-- Corpo do Relato / Observação Principal -->
                            @if(!empty($evento['conteudo']))
                                <div class="text-xs text-gray-700 dark:text-gray-300 bg-gray-50/80 dark:bg-gray-800/50 rounded-xl p-3 border border-gray-100 dark:border-gray-800 leading-relaxed whitespace-pre-line mb-3">
                                    {{ $evento['conteudo'] }}
                                </div>
                            @endif

                            <!-- ========================================== -->
                            <!-- SE FOR VISITA: BLOCO NPS & PESQUISA        -->
                            <!-- ========================================== -->
                            @if($evento['tipo'] === 'visita' && !empty($evento['detalhes']))
                                @php $det = $evento['detalhes']; @endphp
                                
                                @if($det['pesquisa_respondida'])
                                    <div class="rounded-xl border border-teal-200 dark:border-teal-900/60 bg-teal-50/60 dark:bg-teal-950/20 p-3 text-xs space-y-2 mb-2">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-teal-900 dark:text-teal-200 flex items-center gap-1.5">
                                                <span>⭐</span> Avaliação Pós-Tour (NPS: <strong>{{ $det['nota_nps'] }}/10</strong>)
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $det['nota_nps'] >= 9 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' : ($det['nota_nps'] >= 7 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' : 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200') }}">
                                                {{ $det['classificacao_nps'] }}
                                            </span>
                                        </div>

                                        <!-- Dimensões Avaliadas -->
                                        <div class="grid grid-cols-3 gap-2 text-center pt-1 border-t border-teal-200/60 dark:border-teal-900/40">
                                            <div>
                                                <span class="text-[10px] text-teal-700 dark:text-teal-400 block">Atendimento</span>
                                                <span class="font-bold text-teal-950 dark:text-white">{{ $det['nota_atendimento'] ?? '—' }} ⭐</span>
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-teal-700 dark:text-teal-400 block">Infraestrutura</span>
                                                <span class="font-bold text-teal-950 dark:text-white">{{ $det['nota_infraestrutura'] ?? '—' }} ⭐</span>
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-teal-700 dark:text-teal-400 block">Pedagógico</span>
                                                <span class="font-bold text-teal-950 dark:text-white">{{ $det['nota_proposta_pedagogica'] ?? '—' }} ⭐</span>
                                            </div>
                                        </div>

                                        @if(!empty($det['comentario_pesquisa']))
                                            <p class="italic text-teal-800 dark:text-teal-300 pt-1 border-t border-teal-200/60 dark:border-teal-900/40">
                                                “{{ $det['comentario_pesquisa'] }}”
                                            </p>
                                        @endif
                                    </div>
                                @elseif($det['status'] === 'realizada')
                                    <!-- Visita realizada mas sem pesquisa -->
                                    <div class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 text-xs text-amber-800 dark:text-amber-300">
                                        <span>Pesquisa de satisfação pós-tour ainda não foi preenchida pela família.</span>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            @if(!empty($det['link_whatsapp']))
                                                <a 
                                                    href="{{ $det['link_whatsapp'] }}" 
                                                    target="_blank" 
                                                    class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-semibold text-[11px] hover:bg-emerald-700"
                                                >
                                                    Enviar WhatsApp ↗
                                                </a>
                                            @endif
                                            @if(!empty($det['link_pesquisa']))
                                                <a 
                                                    href="{{ $det['link_pesquisa'] }}" 
                                                    target="_blank" 
                                                    class="px-2.5 py-1 rounded-lg bg-amber-600 text-white font-semibold text-[11px] hover:bg-amber-700"
                                                >
                                                    Link da Pesquisa ↗
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endif

                            <!-- ========================================== -->
                            <!-- SE FOR DOCUMENTO: PARECER DE IA GEMINI     -->
                            <!-- ========================================== -->
                            @if($evento['tipo'] === 'documento' && !empty($evento['detalhes']))
                                @php $detDoc = $evento['detalhes']; @endphp
                                
                                @if($detDoc['tem_analise_ia'])
                                    <div class="rounded-xl border border-violet-200 dark:border-violet-900/60 bg-violet-50/60 dark:bg-violet-950/20 p-3 text-xs space-y-1.5 mb-2">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-violet-900 dark:text-violet-200 flex items-center gap-1.5">
                                                <span>✨</span> Parecer Gemini Vision OCR
                                            </span>
                                            @if(!empty($detDoc['score_confianca']))
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-200 text-violet-800 dark:bg-violet-900 dark:text-violet-200">
                                                    Confiança: {{ $detDoc['score_confianca'] }}%
                                                </span>
                                            @endif
                                        </div>

                                        <p class="text-violet-800 dark:text-violet-300 font-medium">
                                            {{ $detDoc['resumo_ia'] }}
                                        </p>

                                        @if(!empty($detDoc['dados_extraidos']))
                                            <div class="pt-1.5 border-t border-violet-200/60 dark:border-violet-900/40 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-violet-900 dark:text-violet-200">
                                                @foreach($detDoc['dados_extraidos'] as $campo => $valor)
                                                    @if(!is_array($valor) && !blank($valor))
                                                        <span><strong>{{ ucfirst(str_replace('_', ' ', $campo)) }}:</strong> {{ $valor }}</span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif

                                        @if(!empty($detDoc['alertas']))
                                            <div class="pt-1 text-[11px] text-rose-600 dark:text-rose-400 font-medium">
                                                ⚠️ Divergências: {{ implode(' • ', $detDoc['alertas']) }}
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            <!-- Ações Rápidas de Rodapé do Card -->
                            <div class="flex items-center justify-between pt-1 border-t border-gray-100 dark:border-gray-800 text-[11px]">
                                <span class="text-gray-400">
                                    ID do Registro: #{{ $evento['registro_id'] }}
                                </span>

                                @if(!empty($telefoneWhatsapp) && ($evento['tipo'] === 'contato' || $evento['tipo'] === 'visita'))
                                    <a 
                                        href="https://wa.me/{{ $telefoneWhatsapp }}" 
                                        target="_blank"
                                        class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400 hover:underline"
                                    >
                                        <span>💬 Retornar no WhatsApp</span>
                                    </a>
                                @endif
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
