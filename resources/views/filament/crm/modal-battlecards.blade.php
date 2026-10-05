@php
    use App\Models\Concorrente;
    use App\Models\Objecao;
    use App\Services\BattlecardService;

    $concorrentes = Concorrente::ativos()
        ->with('cidade')
        ->withCount('interessadosPerdidos')
        ->orderBy('nome')
        ->get();

    $objecoes = Objecao::ativos()->get();
    $categoriasObjecoes = Objecao::CATEGORIAS;

    /** @var BattlecardService $battlecardService */
    $battlecardService = app(BattlecardService::class);
    $radar = $battlecardService->obterRadarConcorrencia();
@endphp

<div 
    x-data="{ 
        activeTab: 'concorrentes',
        filtroObjecao: 'todos',
        selectedConcorrenteId: {{ $concorrentes->first()?->id ?? 'null' }},
        copiado: false,
        copiarParaTransferencia(texto) {
            navigator.clipboard.writeText(texto);
            this.copiado = true;
            setTimeout(() => this.copiado = false, 2500);
        }
    }" 
    class="space-y-6 text-gray-800 dark:text-gray-100"
>
    {{-- Notificação Toast Flutuante de Copiado --}}
    <div 
        x-show="copiado" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-2 bg-emerald-600 text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-xl border border-emerald-400"
        style="display: none;"
    >
        <x-filament::icon icon="heroicon-o-check-circle" class="w-4 h-4 text-white" />
        <span>Roteiro copiado com sucesso para a área de transferência!</span>
    </div>

    {{-- Banner de Cabeçalho Executivo --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-950 via-slate-900 to-indigo-900 p-6 text-white shadow-xl border border-indigo-500/20">
        <div class="absolute -right-8 -top-8 w-48 h-48 bg-indigo-500/10 rounded-full blur-2xl"></div>
        <div class="absolute -left-8 -bottom-8 w-48 h-48 bg-amber-500/10 rounded-full blur-2xl"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/30 text-indigo-300 border border-indigo-400/30">
                    <x-filament::icon icon="heroicon-o-shield-check" class="w-3.5 h-3.5" />
                    <span>Inteligência Competitiva & Vendas Consultivas</span>
                </div>
                <h2 class="text-xl md:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                    Battlecards Comerciais & Contorno de Objeções
                </h2>
                <p class="text-xs md:text-sm text-slate-300 max-w-2xl">
                    Argumentação ética, pontos fracos e fortes dos colégios da região e roteiros persuasivos para transformar dúvidas das famílias em matrículas confirmadas.
                </p>
            </div>

            @if(isset($lead) && $lead)
                <div class="shrink-0 bg-white/10 backdrop-blur-md border border-white/20 p-3 rounded-xl min-w-[210px]">
                    <span class="text-[10px] uppercase font-bold tracking-wider text-indigo-300 block">Lead em Negociação</span>
                    <p class="font-bold text-white text-sm truncate">{{ $lead->pessoa?->nome ?? 'Família' }}</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold {{ match($lead->temperatura) {
                            'quente' => 'bg-rose-500/30 text-rose-200 border border-rose-400/30',
                            'morno' => 'bg-amber-500/30 text-amber-200 border border-amber-400/30',
                            'frio' => 'bg-sky-500/30 text-sky-200 border border-sky-400/30',
                            default => 'bg-slate-500/30 text-slate-300 border border-slate-400/30',
                        } }}">
                            {{ ucfirst($lead->temperatura ?? 'Sem temperatura') }}
                        </span>
                        @if($lead->lead_score !== null)
                            <span class="text-xs font-mono font-bold text-emerald-400">Score {{ $lead->lead_score }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- Barra de Navegação das Abas --}}
        <div class="relative z-10 flex flex-wrap items-center gap-2 mt-6 pt-4 border-t border-white/10">
            <button 
                type="button"
                @click="activeTab = 'concorrentes'" 
                :class="activeTab === 'concorrentes' ? 'bg-white text-slate-900 shadow-md font-bold' : 'text-slate-300 hover:text-white hover:bg-white/10 font-medium'"
                class="px-4 py-2 rounded-xl text-xs md:text-sm transition-all duration-200 flex items-center gap-2 cursor-pointer"
            >
                <x-filament::icon icon="heroicon-o-building-library" class="w-4 h-4" />
                <span>Colégios Concorrentes ({{ $concorrentes->count() }})</span>
            </button>

            <button 
                type="button"
                @click="activeTab = 'objecoes'" 
                :class="activeTab === 'objecoes' ? 'bg-white text-slate-900 shadow-md font-bold' : 'text-slate-300 hover:text-white hover:bg-white/10 font-medium'"
                class="px-4 py-2 rounded-xl text-xs md:text-sm transition-all duration-200 flex items-center gap-2 cursor-pointer"
            >
                <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" class="w-4 h-4" />
                <span>Matriz de Objeções ({{ $objecoes->count() }})</span>
            </button>

            <button 
                type="button"
                @click="activeTab = 'radar'" 
                :class="activeTab === 'radar' ? 'bg-white text-slate-900 shadow-md font-bold' : 'text-slate-300 hover:text-white hover:bg-white/10 font-medium'"
                class="px-4 py-2 rounded-xl text-xs md:text-sm transition-all duration-200 flex items-center gap-2 cursor-pointer"
            >
                <x-filament::icon icon="heroicon-o-chart-pie" class="w-4 h-4" />
                <span>Radar de Perdas da Escola</span>
            </button>

            <button 
                type="button"
                @click="activeTab = 'principios'" 
                :class="activeTab === 'principios' ? 'bg-white text-slate-900 shadow-md font-bold' : 'text-slate-300 hover:text-white hover:bg-white/10 font-medium'"
                class="px-4 py-2 rounded-xl text-xs md:text-sm transition-all duration-200 flex items-center gap-2 cursor-pointer"
            >
                <x-filament::icon icon="heroicon-o-sparkles" class="w-4 h-4" />
                <span>Regras de Ouro na Venda</span>
            </button>
        </div>
    </div>

    {{-- ABA 1: BATTLECARDS DE COLÉGIOS CONCORRENTES --}}
    <div x-show="activeTab === 'concorrentes'" x-transition class="space-y-6">
        @if($concorrentes->isEmpty())
            <div class="p-8 text-center bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-dashed border-gray-300 dark:border-gray-700">
                <div class="mx-auto w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mb-3">
                    <x-filament::icon icon="heroicon-o-building-office-2" class="w-6 h-6" />
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Nenhum colégio concorrente cadastrado</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md mx-auto mt-1 mb-4">
                    Cadastre os principais colégios da sua cidade/bairro no menu <strong>CRM > Escolas Concorrentes</strong> para alimentar a inteligência de batalha da equipe de matrículas.
                </p>
                <a 
                    href="{{ \App\Filament\Resources\Concorrentes\ConcorrenteResource::getUrl('create') }}" 
                    target="_blank"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-500 shadow-xs"
                >
                    <x-filament::icon icon="heroicon-o-plus" class="w-4 h-4" />
                    Cadastrar Primeiro Concorrente
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                {{-- Coluna da Esquerda: Lista de Seleção de Concorrentes --}}
                <div class="lg:col-span-4 space-y-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 block px-1">
                        Selecione a Escola Concorrente
                    </span>
                    <div class="space-y-2 max-h-[620px] overflow-y-auto pr-1">
                        @foreach($concorrentes as $c)
                            <button
                                type="button"
                                @click="selectedConcorrenteId = {{ $c->id }}"
                                :class="selectedConcorrenteId === {{ $c->id }} 
                                    ? 'bg-indigo-50/80 dark:bg-indigo-950/60 border-indigo-500 ring-2 ring-indigo-500/20 shadow-sm' 
                                    : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 hover:border-indigo-300 dark:hover:border-indigo-700'"
                                class="w-full text-left p-3.5 rounded-xl border transition-all duration-150 cursor-pointer block group"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="space-y-0.5">
                                        <p class="font-bold text-sm text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                            {{ $c->nome }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $c->bairro ?? 'Bairro não informado' }} {{ $c->cidade ? ' • ' . $c->cidade->nome : '' }}
                                        </p>
                                    </div>
                                    @if($c->sigla)
                                        <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 shrink-0">
                                            {{ $c->sigla }}
                                        </span>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 mt-2.5 pt-2 border-t border-gray-100 dark:border-gray-800/80 text-[11px]">
                                    <span class="px-2 py-0.5 rounded-full font-medium {{ match($c->faixa_preco) {
                                        'mais_barato' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
                                        'equivalente' => 'bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800',
                                        'mais_caro' => 'bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
                                        default => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
                                    } }}">
                                        {{ $c->rotuloFaixaPreco() ?? 'Preço n/d' }}
                                    </span>

                                    @if($c->interessados_perdidos_count > 0)
                                        <span class="ml-auto font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1">
                                            <x-filament::icon icon="heroicon-s-arrow-trending-down" class="w-3.5 h-3.5" />
                                            {{ $c->interessados_perdidos_count }} perda(s)
                                        </span>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Coluna da Direita: Battlecard Detalhado do Concorrente Selecionado --}}
                <div class="lg:col-span-8">
                    @foreach($concorrentes as $c)
                        <div 
                            x-show="selectedConcorrenteId === {{ $c->id }}" 
                            x-transition
                            class="space-y-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm"
                        >
                            {{-- Cabeçalho do Card --}}
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-gray-200 dark:border-gray-800">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-xl font-black text-gray-900 dark:text-white">
                                            {{ $c->nome }}
                                        </h3>
                                        @if($c->sigla)
                                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                                {{ $c->sigla }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $c->bairro ?? 'Região' }} {{ $c->cidade ? '— ' . $c->cidade->nome : '' }}
                                        @if($c->mensalidade_estimada)
                                            • <strong class="text-gray-700 dark:text-gray-300">Mensalidade estimada:</strong> R$ {{ number_format((float) $c->mensalidade_estimada, 2, ',', '.') }}
                                        @endif
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <a 
                                        href="{{ \App\Filament\Resources\Concorrentes\ConcorrenteResource::getUrl('edit', ['record' => $c->id]) }}" 
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <x-filament::icon icon="heroicon-o-pencil-square" class="w-3.5 h-3.5" />
                                        Editar Ficha
                                    </a>
                                </div>
                            </div>

                            {{-- Proposta Pedagógica deles --}}
                            @if($c->proposta_pedagogica)
                                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 text-xs">
                                    <span class="font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider block mb-1">
                                        Proposta Pedagógica & Perfil do Colégio Concorrente
                                    </span>
                                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                                        {{ $c->proposta_pedagogica }}
                                    </p>
                                </div>
                            @endif

                            {{-- Grade de Análise Competitiva: Eles vs Nós --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- O LADO DELES: Pontos Fortes e Fracos --}}
                                <div class="space-y-4">
                                    {{-- Pontos Fortes Deles --}}
                                    <div class="p-4 rounded-xl bg-sky-50/60 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800/80 space-y-2">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-sky-800 dark:text-sky-300 uppercase tracking-wider">
                                            <x-filament::icon icon="heroicon-s-star" class="w-4 h-4 text-sky-600" />
                                            <span>O que atrai as famílias neles (Pontos Fortes)</span>
                                        </div>
                                        @if(!empty($c->pontos_fortes) && is_array($c->pontos_fortes))
                                            <ul class="space-y-1.5 text-xs text-sky-950 dark:text-sky-200">
                                                @foreach($c->pontos_fortes as $ponto)
                                                    <li class="flex items-start gap-2">
                                                        <span class="text-sky-500 font-bold shrink-0">•</span>
                                                        <span>{{ $ponto }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="text-xs text-gray-400 italic">Nenhum ponto forte documentado ainda.</p>
                                        @endif
                                    </div>

                                    {{-- Vulnerabilidades / Pontos Fracos Deles --}}
                                    <div class="p-4 rounded-xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/80 space-y-2">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wider">
                                            <x-filament::icon icon="heroicon-s-exclamation-triangle" class="w-4 h-4 text-rose-600" />
                                            <span>Vulnerabilidades & Onde Eles Falham</span>
                                        </div>
                                        @if(!empty($c->pontos_fracos) && is_array($c->pontos_fracos))
                                            <ul class="space-y-1.5 text-xs text-rose-950 dark:text-rose-200">
                                                @foreach($c->pontos_fracos as $fraco)
                                                    <li class="flex items-start gap-2">
                                                        <span class="text-rose-500 font-bold shrink-0">⚠️</span>
                                                        <span>{{ $fraco }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="text-xs text-gray-400 italic">Nenhuma vulnerabilidade mapeada ainda.</p>
                                        @endif
                                    </div>
                                </div>

                                {{-- O NOSSO LADO: Nossos Diferenciais e Estratégia --}}
                                <div class="space-y-4">
                                    {{-- Nossos Diferenciais Contra Eles --}}
                                    <div class="p-4 rounded-xl bg-emerald-50/80 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 space-y-2">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">
                                            <x-filament::icon icon="heroicon-s-check-badge" class="w-4 h-4 text-emerald-600" />
                                            <span>Por que a Torre de Marfim é Superior (Nossos Diferenciais)</span>
                                        </div>
                                        @if($c->diferenciais_nossos)
                                            <div class="text-xs text-emerald-950 dark:text-emerald-100 whitespace-pre-line leading-relaxed">
                                                {{ $c->diferenciais_nossos }}
                                            </div>
                                        @else
                                            <p class="text-xs text-gray-400 italic">Cadastre os diferenciais da nossa escola contra este concorrente na edição do cadastro.</p>
                                        @endif
                                    </div>

                                    {{-- Estratégia de Abordagem / Dica de Ouro --}}
                                    <div class="p-4 rounded-xl bg-amber-50/70 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 space-y-2">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-amber-900 dark:text-amber-300 uppercase tracking-wider">
                                            <x-filament::icon icon="heroicon-s-light-bulb" class="w-4 h-4 text-amber-600" />
                                            <span>Roteiro & Estratégia de Abordagem para o Consultor</span>
                                        </div>
                                        @if($c->estrategia_abordagem)
                                            <div class="text-xs text-amber-950 dark:text-amber-100 whitespace-pre-line leading-relaxed italic">
                                                "{{ $c->estrategia_abordagem }}"
                                            </div>
                                        @else
                                            <p class="text-xs text-gray-400 italic">Dica: Nunca ataque o concorrente. Destaque nossa proximidade pedagógica e acolhimento com a criança.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ABA 2: MATRIZ DE OBJEÇÕES E RESPOSTAS PRONTAS --}}
    <div x-show="activeTab === 'objecoes'" x-transition class="space-y-6">
        {{-- Filtros de Categoria em Pílulas --}}
        <div class="flex items-center gap-2 overflow-x-auto pb-1">
            <button
                type="button"
                @click="filtroObjecao = 'todos'"
                :class="filtroObjecao === 'todos' 
                    ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold shadow-xs' 
                    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                class="px-3.5 py-1.5 rounded-full text-xs transition-colors shrink-0 cursor-pointer"
            >
                Todas as Objeções ({{ $objecoes->count() }})
            </button>

            @foreach($categoriasObjecoes as $chave => $rotulo)
                @php
                    $totalNaCat = $objecoes->where('categoria', $chave)->count();
                @endphp
                <button
                    type="button"
                    @click="filtroObjecao = '{{ $chave }}'"
                    :class="filtroObjecao === '{{ $chave }}' 
                        ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold shadow-xs' 
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                    class="px-3.5 py-1.5 rounded-full text-xs transition-colors shrink-0 cursor-pointer"
                >
                    {{ $rotulo }} ({{ $totalNaCat }})
                </button>
            @endforeach

            <div class="ml-auto shrink-0">
                <a 
                    href="{{ \App\Filament\Resources\Objecoes\ObjecaoResource::getUrl('create') }}" 
                    target="_blank"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition-colors"
                >
                    <x-filament::icon icon="heroicon-o-plus" class="w-3.5 h-3.5" />
                    Nova Objeção
                </a>
            </div>
        </div>

        {{-- Lista de Objeções --}}
        @if($objecoes->isEmpty())
            <div class="p-8 text-center bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-dashed border-gray-300 dark:border-gray-700">
                <div class="mx-auto w-12 h-12 rounded-full bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center text-rose-600 dark:text-rose-400 mb-3">
                    <x-filament::icon icon="heroicon-o-shield-exclamation" class="w-6 h-6" />
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Nenhuma objeção cadastrada na matriz</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md mx-auto mt-1 mb-4">
                    Cadastre as principais dúvidas dos pais (ex: Preço Alto, Distância, Proposta Construtivista) no menu <strong>CRM > Matriz de Objeções</strong>.
                </p>
                <a 
                    href="{{ \App\Filament\Resources\Objecoes\ObjecaoResource::getUrl('create') }}" 
                    target="_blank"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-500 shadow-xs"
                >
                    <x-filament::icon icon="heroicon-o-plus" class="w-4 h-4" />
                    Adicionar Objeção & Script
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($objecoes as $obj)
                    <div 
                        x-show="filtroObjecao === 'todos' || filtroObjecao === '{{ $obj->categoria }}'"
                        x-transition
                        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-xs space-y-3.5 flex flex-col justify-between"
                    >
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ match($obj->categoria) {
                                    'preco' => 'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
                                    'distancia' => 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
                                    'pedagogico' => 'bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800',
                                    'estrutura' => 'bg-teal-100 dark:bg-teal-950/60 text-teal-700 dark:text-teal-300 border border-teal-200 dark:border-teal-800',
                                    'vagas' => 'bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800',
                                    default => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
                                } }}">
                                    {{ $obj->rotuloCategoria() }}
                                </span>

                                <a 
                                    href="{{ \App\Filament\Resources\Objecoes\ObjecaoResource::getUrl('edit', ['record' => $obj->id]) }}" 
                                    target="_blank"
                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
                                    title="Editar esta objeção"
                                >
                                    <x-filament::icon icon="heroicon-o-pencil" class="w-3.5 h-3.5" />
                                </a>
                            </div>

                            <h4 class="font-bold text-sm text-gray-900 dark:text-white leading-snug">
                                "{{ $obj->titulo }}"
                            </h4>

                            @if($obj->descricao)
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $obj->descricao }}
                                </p>
                            @endif
                        </div>

                        {{-- Dicas de Postura --}}
                        @if($obj->dicas_postura)
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-[11px] text-slate-700 dark:text-slate-300 flex items-start gap-2">
                                <x-filament::icon icon="heroicon-o-light-bulb" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
                                <div>
                                    <strong class="font-semibold block">Raciocínio & Postura:</strong>
                                    {{ $obj->dicas_postura }}
                                </div>
                            </div>
                        @endif

                        {{-- Script de Resposta Sugerida --}}
                        <div class="p-3.5 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/80 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-900 dark:text-indigo-300 flex items-center gap-1">
                                    <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="w-3.5 h-3.5" />
                                    O que responder (Roteiro Verbal)
                                </span>
                                <button
                                    type="button"
                                    @click="copiarParaTransferencia({{ json_encode($obj->resposta_sugerida) }})"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-700 dark:text-indigo-300 hover:text-indigo-900 dark:hover:text-white transition-colors cursor-pointer"
                                    title="Copiar texto da resposta"
                                >
                                    <x-filament::icon icon="heroicon-o-clipboard-document" class="w-3.5 h-3.5" />
                                    <span>Copiar</span>
                                </button>
                            </div>
                            <p class="text-xs text-indigo-950 dark:text-indigo-100 leading-relaxed italic">
                                "{{ $obj->resposta_sugerida }}"
                            </p>
                        </div>

                        {{-- Pergunta de Virada --}}
                        @if($obj->pergunta_virada)
                            <div class="p-3 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80 space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300 flex items-center gap-1">
                                    <x-filament::icon icon="heroicon-o-arrow-path" class="w-3.5 h-3.5" />
                                    Pergunta de Ouro para Virar o Diálogo
                                </span>
                                <p class="text-xs font-semibold text-emerald-950 dark:text-emerald-100">
                                    "{{ $obj->pergunta_virada }}"
                                </p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ABA 3: RADAR DE PERDAS & INTELIGÊNCIA DE MERCADO --}}
    <div x-show="activeTab === 'radar'" x-transition class="space-y-6">
        {{-- Cards de Métricas Gerais --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-400 block mb-1">Concorrentes Monitorados</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ $radar['total_concorrentes'] }}</span>
                    <span class="text-xs text-gray-500">escolas ativas</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-400 block mb-1">Leads Perdidos para Outras Escolas</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-rose-600 dark:text-rose-400">{{ $radar['total_perdas'] }}</span>
                    <span class="text-xs text-gray-500">famílias migradas</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-400 block mb-1">Fator Decisivo Mais Frequente</span>
                <div class="flex items-baseline gap-2">
                    @php
                        $topFator = $radar['fatores_decisao']->first();
                    @endphp
                    <span class="text-base font-bold text-gray-900 dark:text-white truncate">
                        {{ $topFator['fator'] ?? 'Ainda sem dados' }}
                    </span>
                    @if($topFator)
                        <span class="text-xs font-mono font-bold text-rose-500">({{ $topFator['percentual'] }}%)</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Gráfico / Listagem de Concorrentes que Mais Ganharam Alunos --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Ranking de Concorrentes --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-trophy" class="w-4 h-4 text-amber-500" />
                            Colégios que Mais Captaram Nossos Leads
                        </h4>
                        <p class="text-xs text-gray-400">Escolas que as famílias escolheram ao encerrar negociação</p>
                    </div>
                </div>

                @if($radar['ranking']->isEmpty())
                    <div class="py-8 text-center text-xs text-gray-400">
                        Nenhuma perda vinculada a concorrente registrada até o momento.
                        <p class="text-[11px] text-gray-500 mt-1">Ao descartar um lead no funil ou na tabela, informe a escola concorrente para alimentar este ranking.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($radar['ranking'] as $item)
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                                        <span class="w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-[10px] flex items-center justify-center font-bold">
                                            {{ $loop->iteration }}
                                        </span>
                                        {{ $item['nome'] }} {{ $item['sigla'] ? '('.$item['sigla'].')' : '' }}
                                    </span>
                                    <span class="font-mono text-gray-500 dark:text-gray-400">
                                        <strong class="text-gray-900 dark:text-white">{{ $item['perdas'] }}</strong> ({{ $item['percentual'] }}%)
                                    </span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-800 h-2 rounded-full overflow-hidden">
                                    <div 
                                        class="bg-indigo-600 dark:bg-indigo-500 h-full rounded-full transition-all duration-500" 
                                        style="width: {{ $item['percentual'] }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Fatores Decisivos Alegados pelas Famílias --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-scale" class="w-4 h-4 text-indigo-500" />
                            Motivos Decisivos Alegados pelos Pais
                        </h4>
                        <p class="text-xs text-gray-400">Qual foi o fator decisivo para a escolha do concorrente</p>
                    </div>
                </div>

                @if($radar['fatores_decisao']->isEmpty())
                    <div class="py-8 text-center text-xs text-gray-400">
                        Nenhum fator decisivo registrado ainda.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($radar['fatores_decisao'] as $fator)
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">
                                        {{ $fator['fator'] }}
                                    </span>
                                    <span class="font-mono text-gray-500">
                                        <strong class="text-gray-900 dark:text-white">{{ $fator['total'] }}</strong> ({{ $fator['percentual'] }}%)
                                    </span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-800 h-2 rounded-full overflow-hidden">
                                    <div 
                                        class="bg-rose-500 dark:bg-rose-400 h-full rounded-full transition-all duration-500" 
                                        style="width: {{ $fator['percentual'] }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ABA 4: REGRAS DE OURO NA VENDA CONSULTIVA ESCOLAR --}}
    <div x-show="activeTab === 'principios'" x-transition class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-2 shadow-xs">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-sm">
                    1
                </div>
                <h4 class="font-bold text-sm text-gray-900 dark:text-white">Nunca Ataque a Concorrência</h4>
                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                    Pais enxergam críticas a outros colégios como falta de profissionalismo e desespero. Valide que o outro colégio tem méritos, mas demonstre onde o <strong>Torre de Marfim</strong> é insubstituível para a formação do filho deles.
                </p>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-2 shadow-xs">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/80 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-sm">
                    2
                </div>
                <h4 class="font-bold text-sm text-gray-900 dark:text-white">Venda a Transformação, Não Prédios</h4>
                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                    Paredes, quadras e tablets qualquer colégio pode comprar. O que os pais compram é a segurança de que o filho será acolhido, amado, desafiado e preparado como líder com valores éticos.
                </p>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-2 shadow-xs">
                <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/80 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-sm">
                    3
                </div>
                <h4 class="font-bold text-sm text-gray-900 dark:text-white">Faça Perguntas Abertas de Ouro</h4>
                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                    Antes de tentar rebater uma objeção, pergunte: <em>"O que é mais importante para vocês na formação do seu filho neste momento?"</em> Deixe a família falar 80% do tempo.
                </p>
            </div>
        </div>

        <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white border border-indigo-500/20 space-y-3">
            <h4 class="font-bold text-sm text-indigo-300 uppercase tracking-wider flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-book-open" class="w-4 h-4" />
                Matriz Rápida de Posicionamento Torre de Marfim
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                    <span class="font-bold text-amber-300 block">Proximidade & Família</span>
                    <p class="text-slate-300 text-[11px]">Coordenação com portas abertas e atendimento humanizado individualizado.</p>
                </div>
                <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                    <span class="font-bold text-sky-300 block">Formação Integral</span>
                    <p class="text-slate-300 text-[11px]">Equilíbrio entre excelência acadêmica e desenvolvimento socioemocional sólido.</p>
                </div>
                <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                    <span class="font-bold text-emerald-300 block">Segurança & Rotina</span>
                    <p class="text-slate-300 text-[11px]">Controle rigoroso de portaria, ambiente seguro e comunicação diária com pais.</p>
                </div>
                <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-1">
                    <span class="font-bold text-purple-300 block">Continuidade de Ciclos</span>
                    <p class="text-slate-300 text-[11px]">Acompanhamento da evolução pedagógica do aluno da infância à conclusão.</p>
                </div>
            </div>
        </div>
    </div>
</div>
