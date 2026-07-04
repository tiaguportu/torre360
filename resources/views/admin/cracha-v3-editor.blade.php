<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Editor de Crachá V3 — {{ $templateCrachaV3->nome }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Moveable Local -->
    <script src="{{ asset('js/moveable.min.js') }}"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; }

        .scrollbar-thin::-webkit-scrollbar { width: 5px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: rgba(100,116,139,0.5); border-radius: 4px; }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: rgba(100,116,139,0.8); }

        /* Canvas */
        #cracha-canvas {
            position: relative;
            overflow: visible;
            box-shadow: 0 0 0 1px rgba(139,92,246,0.3), 0 25px 50px -12px rgba(0,0,0,0.8);
        }

        /* Elementos no canvas */
        .cracha-elemento {
            position: absolute;
            cursor: move;
            user-select: none;
            min-width: 20px;
            min-height: 16px;
        }

        .cracha-elemento:focus { outline: none; }

        .cracha-elemento.selecionado {
            z-index: 100;
        }

        /* Tipos de elementos */
        .el-texto, .el-variavel {
            display: flex;
            align-items: center;
        }

        .el-retangulo {
            border: 2px solid #6366f1;
        }

        .el-imagem-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #8b5cf6;
            background: rgba(139,92,246,0.1);
        }

        /* Chip de variável */
        .variavel-chip {
            position: absolute;
            top: -18px;
            left: 0;
            background: #7c3aed;
            color: #fff;
            font-size: 8px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 3px;
            letter-spacing: 0.03em;
            white-space: nowrap;
            pointer-events: none;
        }

        /* Moveable overrides */
        .moveable-control-box {
            --moveable-color: #8b5cf6 !important;
        }
        .moveable-line { background: #8b5cf6 !important; }
        .moveable-control {
            background: #fff !important;
            border-color: #8b5cf6 !important;
        }
        .moveable-rotation-control { background: #8b5cf6 !important; }

        /* Régua de zoom */
        .zoom-badge {
            background: rgba(139,92,246,0.15);
            border: 1px solid rgba(139,92,246,0.3);
            color: #a78bfa;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 999px;
        }

        /* Toast */
        .toast {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            transition: all 0.3s ease;
        }

        /* Botão de variável */
        .btn-variavel {
            @apply flex items-center justify-between w-full px-3 py-2 rounded-lg border text-xs font-medium transition-all duration-150 cursor-pointer;
        }

        /* Input range custom */
        input[type=range] {
            -webkit-appearance: none;
            appearance: none;
            height: 4px;
            border-radius: 2px;
            background: #334155;
            outline: none;
        }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #8b5cf6;
            cursor: pointer;
            border: 2px solid #fff;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 flex flex-col h-screen overflow-hidden">

    <!-- ===== HEADER ===== -->
    <header class="bg-slate-900 border-b border-slate-800 px-5 py-3 flex items-center justify-between z-30 shadow-lg shrink-0">
        <div class="flex items-center gap-3">
            <div class="bg-violet-600 text-white font-black p-1.5 rounded-lg text-xs shadow-md shadow-violet-600/30 tracking-tight">CR-V3</div>
            <div>
                <h1 class="text-base font-bold tracking-tight text-white leading-tight">{{ $templateCrachaV3->nome }}</h1>
                <p class="text-[11px] text-slate-400 leading-none mt-0.5">
                    <span class="text-violet-400 font-semibold">{{ $templateCrachaV3->largura }}×{{ $templateCrachaV3->altura }} px</span>
                    <span class="mx-1.5 opacity-40">•</span>
                    Entidade: <span class="text-blue-400 font-semibold capitalize">{{ $templateCrachaV3->tipo_entidade->value ?? $templateCrachaV3->tipo_entidade }}</span>
                    <span class="mx-1.5 opacity-40">•</span>
                    Engine: <span class="text-violet-400 font-semibold">Moveable V3</span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Zoom -->
            <div class="flex items-center gap-2 bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5">
                <button onclick="ajustarZoom(-0.1)" class="text-slate-400 hover:text-white transition-colors text-base font-bold w-5 h-5 flex items-center justify-center">−</button>
                <span id="zoom-label" class="zoom-badge">100%</span>
                <button onclick="ajustarZoom(0.1)" class="text-slate-400 hover:text-white transition-colors text-base font-bold w-5 h-5 flex items-center justify-center">+</button>
            </div>

            <!-- Undo/Redo -->
            <div class="flex items-center gap-1">
                <button onclick="undo()" title="Desfazer (Ctrl+Z)" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-400 hover:text-white p-2 rounded-lg transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h10a5 5 0 015 5v1M3 10l4-4M3 10l4 4"/></svg>
                </button>
                <button onclick="redo()" title="Refazer (Ctrl+Y)" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-400 hover:text-white p-2 rounded-lg transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 10H11a5 5 0 00-5 5v1M21 10l-4-4M21 10l-4 4"/></svg>
                </button>
            </div>

            <!-- Cor de fundo -->
            <div class="flex items-center gap-1.5 bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5">
                <label for="cor-fundo" class="text-[10px] text-slate-400 font-medium">Fundo</label>
                <input type="color" id="cor-fundo" value="#ffffff" class="w-6 h-6 rounded cursor-pointer border-0 bg-transparent" title="Cor de fundo do crachá">
            </div>

            <div id="toast-area"></div>

            <button onclick="salvarLayout()" class="bg-violet-600 hover:bg-violet-500 active:scale-95 text-white px-5 py-2 rounded-lg font-semibold text-sm transition-all duration-200 shadow-md shadow-violet-600/20 flex items-center gap-2 border border-violet-500/30">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Salvar
            </button>
            <button onclick="window.close()" class="bg-slate-800 hover:bg-slate-700 active:scale-95 text-slate-300 px-4 py-2 rounded-lg font-semibold text-sm transition-all duration-200 border border-slate-700">
                Fechar
            </button>
        </div>
    </header>

    <!-- ===== WORKSPACE ===== -->
    <div class="flex flex-1 overflow-hidden">

        <!-- ===== SIDEBAR ESQUERDA ===== -->
        <aside class="w-72 bg-slate-900 border-r border-slate-800 flex flex-col z-20 shrink-0">

            <!-- Tabs -->
            <div class="flex border-b border-slate-800 shrink-0">
                <button id="tab-elementos" onclick="mudarTab('elementos')" class="flex-1 px-3 py-3 text-xs font-semibold border-b-2 border-violet-500 text-violet-400 transition-colors">
                    Elementos
                </button>
                <button id="tab-propriedades" onclick="mudarTab('propriedades')" class="flex-1 px-3 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-300 transition-colors">
                    Propriedades
                </button>
            </div>

            <!-- Painel: Elementos -->
            <div id="painel-elementos" class="flex-1 overflow-y-auto scrollbar-thin p-4 space-y-5">

                <!-- Elementos Estáticos -->
                <div>
                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2.5">Inserir Elemento</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="adicionarElemento('texto')"
                            class="flex flex-col items-center gap-1.5 p-3 bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-violet-500/50 rounded-xl transition-all text-xs font-medium text-slate-300 group active:scale-95">
                            <svg class="w-5 h-5 text-violet-400 group-hover:text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/></svg>
                            Texto
                        </button>
                        <button onclick="adicionarElemento('retangulo')"
                            class="flex flex-col items-center gap-1.5 p-3 bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-violet-500/50 rounded-xl transition-all text-xs font-medium text-slate-300 group active:scale-95">
                            <svg class="w-5 h-5 text-violet-400 group-hover:text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/></svg>
                            Retângulo
                        </button>
                        <button onclick="adicionarElemento('circulo')"
                            class="flex flex-col items-center gap-1.5 p-3 bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-violet-500/50 rounded-xl transition-all text-xs font-medium text-slate-300 group active:scale-95">
                            <svg class="w-5 h-5 text-violet-400 group-hover:text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"/></svg>
                            Círculo
                        </button>
                        <button onclick="adicionarElemento('linha')"
                            class="flex flex-col items-center gap-1.5 p-3 bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-violet-500/50 rounded-xl transition-all text-xs font-medium text-slate-300 group active:scale-95">
                            <svg class="w-5 h-5 text-violet-400 group-hover:text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2.5" d="M4 12h16"/></svg>
                            Linha
                        </button>
                        <button onclick="document.getElementById('upload-imagem-input').click()"
                            class="col-span-2 flex items-center justify-center gap-2 p-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 hover:border-violet-500/50 rounded-xl transition-all text-xs font-medium text-slate-300 group active:scale-95">
                            <svg class="w-4 h-4 text-violet-400 group-hover:text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Importar Imagem (Local)
                        </button>
                    </div>
                </div>

                <!-- Variáveis de Pessoa -->
                <div>
                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-emerald-500 mb-2.5 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>
                        Pessoa
                    </h3>
                    <div class="space-y-1.5">
                        @foreach ($todasVariaveis['pessoa']['Variáveis de Pessoa'] ?? [] as $key => $label)
                            <button onclick="adicionarVariavel('{{ $key }}', '{{ $label }}')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg border bg-slate-800 hover:bg-emerald-950/40 border-slate-700 hover:border-emerald-500/50 text-xs font-medium transition-all duration-150 active:scale-95 group">
                                <span class="text-slate-200 group-hover:text-emerald-300">{{ $label }}</span>
                                <span class="font-mono text-[9px] text-slate-500 group-hover:text-emerald-500 bg-slate-950/60 px-1.5 py-0.5 rounded">{{ $key }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Variáveis de Turma -->
                @if ($templateCrachaV3->tipo_entidade->value === 'turma')
                <div>
                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-blue-500 mb-2.5 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 inline-block"></span>
                        Turma
                    </h3>
                    <div class="space-y-1.5">
                        @foreach ($todasVariaveis['turma']['Variáveis de Turma'] ?? [] as $key => $label)
                            <button onclick="adicionarVariavel('{{ $key }}', '{{ $label }}')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg border bg-slate-800 hover:bg-blue-950/40 border-slate-700 hover:border-blue-500/50 text-xs font-medium transition-all duration-150 active:scale-95 group">
                                <span class="text-slate-200 group-hover:text-blue-300">{{ $label }}</span>
                                <span class="font-mono text-[9px] text-slate-500 group-hover:text-blue-500 bg-slate-950/60 px-1.5 py-0.5 rounded">{{ $key }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif

                @if ($templateCrachaV3->tipo_entidade->value === 'turma')
                <div>
                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2.5 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400 inline-block"></span>
                        Aluno (na Turma)
                    </h3>
                    <div class="space-y-1.5">
                        @foreach ($todasVariaveis['turma']['Variáveis do Aluno (Pessoa)'] ?? [] as $key => $label)
                            <button onclick="adicionarVariavel('{{ $key }}', '{{ $label }}')"
                                class="flex items-center justify-between w-full px-3 py-2 rounded-lg border bg-slate-800 hover:bg-slate-700/60 border-slate-700 hover:border-slate-500/50 text-xs font-medium transition-all duration-150 active:scale-95 group">
                                <span class="text-slate-200 group-hover:text-slate-100">{{ $label }}</span>
                                <span class="font-mono text-[9px] text-slate-500 bg-slate-950/60 px-1.5 py-0.5 rounded">{{ $key }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>

            <!-- Painel: Propriedades -->
            <div id="painel-propriedades" class="hidden flex-1 overflow-y-auto scrollbar-thin p-4">
                <div id="sem-selecao" class="flex flex-col items-center justify-center h-48 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-800 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
                    </div>
                    <p class="text-xs text-slate-600 font-medium">Selecione um elemento<br>no canvas para editar</p>
                </div>

                <div id="painel-props-conteudo" class="hidden space-y-4">

                    <!-- Info do elemento -->
                    <div class="bg-slate-800 rounded-xl p-3 space-y-1">
                        <p class="text-[10px] text-slate-500 uppercase tracking-wider font-bold">Elemento Selecionado</p>
                        <p id="prop-tipo" class="text-xs font-semibold text-violet-400"></p>
                        <p id="prop-variavel-label" class="text-[10px] text-slate-400 hidden"></p>
                    </div>

                    <!-- Formato Foto -->
                    <div id="grupo-foto-formato" class="hidden">
                        <p class="text-[10px] text-slate-500 uppercase tracking-wider font-bold mb-2">Formato da Foto</p>
                        <select id="prop-foto-formato" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:border-violet-500 focus:outline-none" onchange="aplicarFormatoFoto(this.value)">
                            <option value="retangulo">Retângulo</option>
                            <option value="arredondado">Canto Arredondado</option>
                            <option value="circulo">Círculo / Elipse</option>
                        </select>
                    </div>

                    <!-- Posição/Tamanho -->
                    <div>
                        <p class="text-[10px] text-slate-500 uppercase tracking-wider font-bold mb-2">Posição e Tamanho</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] text-slate-500 mb-1 block">X</label>
                                <input type="number" id="prop-x" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:border-violet-500 focus:outline-none" onchange="aplicarPosicao()">
                            </div>
                            <div>
                                <label class="text-[10px] text-slate-500 mb-1 block">Y</label>
                                <input type="number" id="prop-y" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:border-violet-500 focus:outline-none" onchange="aplicarPosicao()">
                            </div>
                            <div>
                                <label class="text-[10px] text-slate-500 mb-1 block">Largura</label>
                                <input type="number" id="prop-w" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:border-violet-500 focus:outline-none" onchange="aplicarTamanho()">
                            </div>
                            <div>
                                <label class="text-[10px] text-slate-500 mb-1 block">Altura</label>
                                <input type="number" id="prop-h" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:border-violet-500 focus:outline-none" onchange="aplicarTamanho()">
                            </div>
                        </div>
                    </div>

                    <!-- Rotação -->
                    <div>
                        <p class="text-[10px] text-slate-500 uppercase tracking-wider font-bold mb-2">Rotação</p>
                        <div class="flex items-center gap-3">
                            <input type="range" id="prop-rotacao" min="-180" max="180" value="0" class="flex-1" oninput="aplicarRotacao(this.value)">
                            <span id="prop-rotacao-label" class="text-xs font-mono text-violet-400 w-10 text-right">0°</span>
                        </div>
                    </div>

                    <!-- Texto -->
                    <div id="grupo-texto">
                        <p class="text-[10px] text-slate-500 uppercase tracking-wider font-bold mb-2">Texto</p>
                        <div class="space-y-2">
                            <div>
                                <label class="text-[10px] text-slate-500 mb-1 block">Conteúdo</label>
                                <textarea id="prop-conteudo" rows="2" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:border-violet-500 focus:outline-none resize-none" oninput="aplicarConteudo()"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] text-slate-500 mb-1 block">Tamanho (px)</label>
                                    <input type="number" id="prop-font-size" value="14" min="6" max="120" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:border-violet-500 focus:outline-none" oninput="aplicarEstilo()">
                                </div>
                                <div>
                                    <label class="text-[10px] text-slate-500 mb-1 block">Peso</label>
                                    <select id="prop-font-weight" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:border-violet-500 focus:outline-none" onchange="aplicarEstilo()">
                                        <option value="300">Leve</option>
                                        <option value="400">Normal</option>
                                        <option value="600" selected>Semi-negrito</option>
                                        <option value="700">Negrito</option>
                                        <option value="800">Extra-negrito</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] text-slate-500 mb-1 block">Alinhamento</label>
                                <div class="flex gap-1">
                                    <button onclick="setAlign('left')" id="align-left" class="flex-1 py-1.5 rounded bg-slate-700 border border-slate-600 text-xs text-slate-300 hover:bg-violet-700 hover:border-violet-500 transition-all">←</button>
                                    <button onclick="setAlign('center')" id="align-center" class="flex-1 py-1.5 rounded bg-violet-700 border border-violet-500 text-xs text-white transition-all">≡</button>
                                    <button onclick="setAlign('right')" id="align-right" class="flex-1 py-1.5 rounded bg-slate-700 border border-slate-600 text-xs text-slate-300 hover:bg-violet-700 hover:border-violet-500 transition-all">→</button>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] text-slate-500 mb-1 block">Cor do texto</label>
                                    <input type="color" id="prop-color" value="#1e293b" class="w-full h-8 rounded cursor-pointer border border-slate-700 bg-transparent" oninput="aplicarEstilo()">
                                </div>
                                <div>
                                    <label class="text-[10px] text-slate-500 mb-1 block">Opacidade</label>
                                    <input type="number" id="prop-opacity" value="100" min="0" max="100" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:border-violet-500 focus:outline-none" oninput="aplicarEstilo()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fundo/Borda do elemento -->
                    <div>
                        <p class="text-[10px] text-slate-500 uppercase tracking-wider font-bold mb-2">Fundo e Borda</p>
                        <div class="space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] text-slate-500 mb-1 block">Cor de fundo</label>
                                    <input type="color" id="prop-bg-color" value="#6366f1" class="w-full h-8 rounded cursor-pointer border border-slate-700 bg-transparent" oninput="aplicarEstilo()">
                                </div>
                                <div>
                                    <label class="text-[10px] text-slate-500 mb-1 block">Transparente</label>
                                    <input type="checkbox" id="prop-bg-transparent" class="mt-2 w-4 h-4 accent-violet-500 cursor-pointer" onchange="aplicarEstilo()">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] text-slate-500 mb-1 block">Cor da borda</label>
                                    <input type="color" id="prop-border-color" value="#8b5cf6" class="w-full h-8 rounded cursor-pointer border border-slate-700 bg-transparent" oninput="aplicarEstilo()">
                                </div>
                                <div>
                                    <label class="text-[10px] text-slate-500 mb-1 block">Espessura (px)</label>
                                    <input type="number" id="prop-border-width" value="0" min="0" max="20" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2 py-1.5 text-xs text-white focus:border-violet-500 focus:outline-none" oninput="aplicarEstilo()">
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] text-slate-500 mb-1 block">Arredondamento (px)</label>
                                <input type="range" id="prop-border-radius" min="0" max="200" value="0" class="w-full" oninput="document.getElementById('prop-border-radius-label').textContent=this.value+'px'; aplicarEstilo()">
                                <div class="flex justify-between mt-1">
                                    <span class="text-[9px] text-slate-600">0</span>
                                    <span id="prop-border-radius-label" class="text-[10px] text-violet-400 font-mono">0px</span>
                                    <span class="text-[9px] text-slate-600">200</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ações -->
                    <div class="pt-2 border-t border-slate-800 space-y-2">
                        <button onclick="duplicarElemento()" class="w-full flex items-center justify-center gap-2 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs text-slate-300 transition-all font-medium active:scale-95">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                            Duplicar
                        </button>
                        <button onclick="deletarElementoSelecionado()" class="w-full flex items-center justify-center gap-2 py-2 rounded-lg bg-rose-950/30 hover:bg-rose-900/40 border border-rose-800/50 text-xs text-rose-400 transition-all font-medium active:scale-95">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Deletar Elemento
                        </button>
                    </div>

                </div>
            </div>

            <!-- Footer da sidebar -->
            <div class="px-4 py-2.5 border-t border-slate-800 shrink-0">
                <p class="text-[9px] text-slate-600 text-center font-mono">Torre360 Badge Engine V3 · Moveable</p>
            </div>
        </aside>

        <!-- ===== CANVAS AREA ===== -->
        <main class="flex-1 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-slate-900 via-slate-950 to-black flex items-center justify-center relative overflow-auto p-8">

            <!-- Guias de grid (decorativo) -->
            <div class="absolute inset-0 opacity-5 pointer-events-none"
                style="background-image: linear-gradient(rgba(139,92,246,0.3) 1px, transparent 1px), linear-gradient(90deg, rgba(139,92,246,0.3) 1px, transparent 1px); background-size: 20px 20px;">
            </div>

            <!-- Wrapper para escala/zoom -->
            <div id="canvas-zoom-wrapper" style="transform-origin: center center; transform: scale(1);">
                <!-- O Crachá -->
                <div id="cracha-canvas"
                    style="width: {{ $templateCrachaV3->largura }}px; height: {{ $templateCrachaV3->altura }}px; background: #ffffff;"
                    onclick="desselecionarTudo(event)">
                    <!-- Elementos serão inseridos aqui via JS -->
                </div>
            </div>

        </main>

    </div>
    <input type="file" id="upload-imagem-input" accept="image/*" class="hidden" onchange="uploadImagemSelecionada(this)">

    <script>
    // =====================================================
    // CONFIGURAÇÃO INICIAL
    // =====================================================
    const TEMPLATE_ID = {{ $templateCrachaV3->id }};
    const CANVAS_W = {{ $templateCrachaV3->largura }};
    const CANVAS_H = {{ $templateCrachaV3->altura }};
    const SAVE_URL = "{{ route('template-crachas-v3.save', $templateCrachaV3->id) }}";
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Layout inicial do banco
    const DADOS_INICIAIS = {!! json_encode($templateCrachaV3->dados_json) !!};

    // =====================================================
    // ESTADO DA APLICAÇÃO E MONITOR DE ERROS
    // =====================================================
    window.addEventListener('error', function(e) {
        if (typeof showToast === 'function') {
            showToast("Erro JS: " + e.message, "error");
        } else {
            alert("Erro JS: " + e.message);
        }
    });

    function updateMoveable() {
        if (!moveableInstance) return;
        if (typeof moveableInstance.updateRect === 'function') {
            moveableInstance.updateRect();
        } else if (typeof moveableInstance.updateTarget === 'function') {
            moveableInstance.updateTarget();
        }
    }

    let elementosData = []; // Array de objetos com todos os dados dos elementos
    let elementoSelecionadoId = null;
    let moveableInstance = null;
    let zoomAtual = 1;
    let historico = [];
    let historicoIndex = -1;
    let contadorId = 1;

    const canvas = document.getElementById('cracha-canvas');
    const zoomWrapper = document.getElementById('canvas-zoom-wrapper');

    // =====================================================
    // INICIALIZAÇÃO
    // =====================================================
    document.addEventListener('DOMContentLoaded', () => {
        // Cor de fundo
        const corFundoInput = document.getElementById('cor-fundo');

        // Carregar dados do banco
        if (DADOS_INICIAIS && DADOS_INICIAIS.elementos && DADOS_INICIAIS.elementos.length > 0) {
            if (DADOS_INICIAIS.fundo) {
                canvas.style.background = DADOS_INICIAIS.fundo;
                corFundoInput.value = rgbToHex(DADOS_INICIAIS.fundo) || DADOS_INICIAIS.fundo;
            }
            DADOS_INICIAIS.elementos.forEach(el => {
                const dom = criarElementoDOM(el);
                canvas.appendChild(dom);
                elementosData.push(el);
                const numId = parseInt(el.id.replace('el_', ''));
                if (numId >= contadorId) { contadorId = numId + 1; }
            });
        }

        // Inicializar Moveable (sem target inicial)
        moveableInstance = new Moveable(document.body, {
            target: null,
            zoom: zoomAtual,
            draggable: true,
            resizable: true,
            rotatable: true,
            snappable: true,
            snapThreshold: 5,
            elementSnapDirections: { top: true, left: true, bottom: true, right: true, center: true, middle: true },
            snapDirections: { top: true, left: true, bottom: true, right: true, center: true, middle: true },
            snapContainer: canvas,
            isDisplaySnapDigit: true,
            throttleDrag: 1,
            throttleResize: 1,
            throttleRotate: 0.5,
            keepRatio: false,
        });

        // Eventos do Moveable
        moveableInstance.on('drag', ({ target, beforeTranslate }) => {
            const el = getElementoData(target.id);
            if (!el) { return; }

            // Calcular nova posição relativa ao canvas
            let newX = Math.round(beforeTranslate[0]);
            let newY = Math.round(beforeTranslate[1]);

            // Clamp dentro do canvas
            newX = Math.max(0, Math.min(newX, CANVAS_W - el.largura));
            newY = Math.max(0, Math.min(newY, CANVAS_H - el.altura));

            el.x = newX;
            el.y = newY;
            target.style.transform = `translate(${newX}px, ${newY}px) rotate(${el.rotacao}deg)`;
            atualizarPainelPosicao(el);
        });

        moveableInstance.on('dragEnd', () => { salvarHistorico(); });

        moveableInstance.on('resize', ({ target, width, height, drag }) => {
            const el = getElementoData(target.id);
            if (!el) { return; }

            el.largura = Math.round(Math.max(20, width));
            el.altura = Math.round(Math.max(16, height));
            el.x = Math.round(drag.beforeTranslate[0]);
            el.y = Math.round(drag.beforeTranslate[1]);

            target.style.width = `${el.largura}px`;
            target.style.height = `${el.altura}px`;
            target.style.transform = `translate(${el.x}px, ${el.y}px) rotate(${el.rotacao}deg)`;
            atualizarPainelPosicao(el);
        });

        moveableInstance.on('resizeEnd', () => { salvarHistorico(); });

        moveableInstance.on('rotate', ({ target, beforeRotation }) => {
            const el = getElementoData(target.id);
            if (!el) { return; }
            el.rotacao = Math.round(beforeRotation);
            target.style.transform = `translate(${el.x}px, ${el.y}px) rotate(${el.rotacao}deg)`;
            document.getElementById('prop-rotacao').value = el.rotacao;
            document.getElementById('prop-rotacao-label').textContent = `${el.rotacao}°`;
        });

        moveableInstance.on('rotateEnd', () => { salvarHistorico(); });

        // Cor de fundo do crachá
        corFundoInput.addEventListener('input', (e) => {
            canvas.style.background = e.target.value;
        });
        corFundoInput.addEventListener('change', () => { salvarHistorico(); });

        // Teclado
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Delete' && elementoSelecionadoId && !document.activeElement.matches('input, textarea, select')) {
                deletarElementoSelecionado();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'z') { e.preventDefault(); undo(); }
            if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'z'))) { e.preventDefault(); redo(); }
            if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); salvarLayout(); }
        });

        // Salvar histórico inicial
        salvarHistorico();
    });

    // =====================================================
    // CRIAÇÃO DE ELEMENTOS DOM
    // =====================================================
    function criarElementoDOM(dadosEl) {
        const el = document.createElement('div');
        el.id = dadosEl.id;
        el.classList.add('cracha-elemento');
        el.setAttribute('data-tipo', dadosEl.tipo);

        if (dadosEl.variavel) {
            el.setAttribute('data-variavel', dadosEl.variavel);
        }

        // Posição e tamanho via transform
        el.style.transform = `translate(${dadosEl.x}px, ${dadosEl.y}px) rotate(${dadosEl.rotacao}deg)`;
        el.style.width = `${dadosEl.largura}px`;
        el.style.height = `${dadosEl.altura}px`;
        el.style.left = '0';
        el.style.top = '0';

        // Aplicar estilos do elemento
        aplicarEstilosNoDOM(el, dadosEl);

        // Conteúdo visual
        if (dadosEl.tipo === 'variavel' && dadosEl.variavel === '{foto}') {
            el.innerHTML = `
                <div class="el-imagem-placeholder w-full h-full flex flex-col items-center justify-center gap-1 border-2 border-dashed border-purple-400/60 bg-purple-950/20 rounded" style="border-radius:${dadosEl.estilos.borderRadius || '0px'}">
                    <svg class="w-6 h-6 text-purple-400/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span class="text-[9px] text-purple-400/60 font-mono font-bold">{foto}</span>
                </div>`;
        } else if (dadosEl.tipo === 'linha') {
            el.innerHTML = `<div style="width:100%; height: ${dadosEl.estilos.borderWidth || '2'}px; background: ${dadosEl.estilos.backgroundColor || '#8b5cf6'}; border-radius: 2px;"></div>`;
            el.style.display = 'flex';
            el.style.alignItems = 'center';
        } else if (dadosEl.tipo === 'retangulo' || dadosEl.tipo === 'circulo') {
            el.innerHTML = '';
        } else if (dadosEl.tipo === 'imagem') {
            el.innerHTML = `<img src="${dadosEl.conteudo}" class="w-full h-full object-contain pointer-events-none" style="display: block; border-radius: ${dadosEl.estilos.borderRadius || '0px'};" />`;
        } else {
            // texto ou variavel de texto
            const innerSpan = document.createElement('span');
            innerSpan.style.display = 'block';
            innerSpan.style.width = '100%';
            innerSpan.textContent = dadosEl.variavel ? dadosEl.variavel : dadosEl.conteudo;
            el.appendChild(innerSpan);

            // Chip de variável
            if (dadosEl.variavel && dadosEl.variavel !== '{foto}') {
                const chip = document.createElement('div');
                chip.className = 'variavel-chip';
                chip.textContent = dadosEl.labelVariavel || dadosEl.variavel;
                el.appendChild(chip);
            }
        }

        // Evento de seleção ao clicar
        el.addEventListener('click', (e) => {
            e.stopPropagation();
            selecionarElemento(dadosEl.id);
        });

        // Duplo clique para editar texto
        el.addEventListener('dblclick', (e) => {
            if (dadosEl.tipo === 'texto' || (dadosEl.tipo === 'variavel' && dadosEl.variavel !== '{foto}')) {
                e.stopPropagation();
                const span = el.querySelector('span');
                if (span) {
                    span.contentEditable = 'true';
                    span.focus();
                    span.addEventListener('blur', () => {
                        span.contentEditable = 'false';
                        dadosEl.conteudo = span.textContent;
                        salvarHistorico();
                    }, { once: true });
                }
            }
        });

        return el;
    }

    function aplicarEstilosNoDOM(dom, dadosEl) {
        const est = dadosEl.estilos || {};
        const tipo = dadosEl.tipo;

        // Reset
        dom.style.fontSize = est.fontSize || '14px';
        dom.style.fontWeight = est.fontWeight || '400';
        dom.style.color = est.color || '#1e293b';
        dom.style.textAlign = est.textAlign || 'left';
        dom.style.opacity = est.opacity !== undefined ? est.opacity / 100 : 1;
        dom.style.borderRadius = est.borderRadius || '0px';
        dom.style.border = est.borderWidth && parseInt(est.borderWidth) > 0
            ? `${est.borderWidth}px solid ${est.borderColor || '#8b5cf6'}`
            : 'none';

        if (tipo === 'linha') {
            dom.style.backgroundColor = 'transparent';
        } else {
            dom.style.backgroundColor = est.bgTransparent ? 'transparent' : (est.backgroundColor || 'transparent');
        }

        if (tipo === 'circulo') {
            dom.style.borderRadius = '50%';
        }

        // Caso especial: Foto do Aluno ou Imagem Importada
        if (tipo === 'imagem' || (tipo === 'variavel' && dadosEl.variavel === '{foto}')) {
            dom.style.overflow = 'hidden';
            const img = dom.querySelector('img');
            if (img) {
                img.style.borderRadius = est.borderRadius || '0px';
            }
            const placeholder = dom.querySelector('.el-imagem-placeholder');
            if (placeholder) {
                placeholder.style.borderRadius = est.borderRadius || '0px';
            }
        }
    }

    // =====================================================
    // ADICIONAR ELEMENTOS
    // =====================================================
    function adicionarElemento(tipo) {
        const id = `el_${contadorId++}`;
        const el = {
            id,
            tipo,
            variavel: null,
            labelVariavel: null,
            conteudo: tipo === 'texto' ? 'Texto aqui' : '',
            x: Math.round(CANVAS_W / 2 - 80),
            y: Math.round(CANVAS_H / 2 - 20),
            largura: tipo === 'linha' ? 200 : (tipo === 'circulo' ? 60 : 160),
            altura: tipo === 'linha' ? 20 : (tipo === 'circulo' ? 60 : (tipo === 'retangulo' ? 50 : 30)),
            rotacao: 0,
            estilos: {
                fontSize: '14px',
                fontWeight: '600',
                color: '#1e293b',
                textAlign: 'center',
                backgroundColor: tipo === 'retangulo' ? '#6366f1' : (tipo === 'circulo' ? '#8b5cf6' : 'transparent'),
                bgTransparent: tipo === 'texto',
                borderColor: '#8b5cf6',
                borderWidth: tipo === 'linha' ? '0' : '0',
                borderRadius: '0px',
                opacity: 100,
            },
        };

        if (tipo === 'linha') {
            el.estilos.backgroundColor = '#8b5cf6';
            el.estilos.bgTransparent = false;
        }

        elementosData.push(el);
        const dom = criarElementoDOM(el);
        canvas.appendChild(dom);
        selecionarElemento(id);
        salvarHistorico();
    }

    function adicionarVariavel(variavel, label) {
        const id = `el_${contadorId++}`;
        const isFoto = variavel === '{foto}';
        const el = {
            id,
            tipo: 'variavel',
            variavel,
            labelVariavel: label,
            conteudo: variavel,
            x: Math.round(CANVAS_W / 2 - (isFoto ? 40 : 80)),
            y: Math.round(CANVAS_H / 2 - (isFoto ? 40 : 15)),
            largura: isFoto ? 80 : 200,
            altura: isFoto ? 80 : 30,
            rotacao: 0,
            estilos: {
                fontSize: '13px',
                fontWeight: '600',
                color: '#1e293b',
                textAlign: 'center',
                backgroundColor: 'transparent',
                bgTransparent: true,
                borderColor: '#8b5cf6',
                borderWidth: '0',
                borderRadius: isFoto ? '50%' : '0px',
                opacity: 100,
            },
        };

        elementosData.push(el);
        const dom = criarElementoDOM(el);
        canvas.appendChild(dom);
        selecionarElemento(id);
        salvarHistorico();
        showToast(`Campo "${label}" adicionado!`, 'success');
    }

    // =====================================================
    // SELEÇÃO E DESSELEÇÃO
    // =====================================================
    function selecionarElemento(id) {
        elementoSelecionadoId = id;
        const dom = document.getElementById(id);
        if (!dom) { return; }

        // Remove selecionado dos outros
        document.querySelectorAll('.cracha-elemento').forEach(el => el.classList.remove('selecionado'));
        dom.classList.add('selecionado');

        // Configura Moveable
        moveableInstance.target = dom;
        updateMoveable();

        // Atualiza painel de propriedades
        mudarTab('propriedades');
        atualizarPainelPropriedades(id);
    }

    function desselecionarTudo(e) {
        if (e.target !== canvas) { return; }
        elementoSelecionadoId = null;
        document.querySelectorAll('.cracha-elemento').forEach(el => el.classList.remove('selecionado'));
        moveableInstance.target = null;
        updateMoveable();

        document.getElementById('painel-props-conteudo').classList.add('hidden');
        document.getElementById('sem-selecao').classList.remove('hidden');
    }

    // =====================================================
    // PAINEL DE PROPRIEDADES
    // =====================================================
    function atualizarPainelPropriedades(id) {
        const el = getElementoData(id);
        if (!el) { return; }

        document.getElementById('sem-selecao').classList.add('hidden');
        document.getElementById('painel-props-conteudo').classList.remove('hidden');

        const tipoLabels = { texto: 'Texto', variavel: 'Campo Dinâmico', retangulo: 'Retângulo', circulo: 'Círculo', linha: 'Linha', imagem: 'Imagem' };
        document.getElementById('prop-tipo').textContent = tipoLabels[el.tipo] || el.tipo;

        const varLabel = document.getElementById('prop-variavel-label');
        if (el.variavel) {
            varLabel.textContent = `Variável: ${el.variavel} — ${el.labelVariavel || ''}`;
            varLabel.classList.remove('hidden');
        } else {
            varLabel.classList.add('hidden');
        }

        // Posição
        document.getElementById('prop-x').value = el.x;
        document.getElementById('prop-y').value = el.y;
        document.getElementById('prop-w').value = el.largura;
        document.getElementById('prop-h').value = el.altura;
        document.getElementById('prop-rotacao').value = el.rotacao;
        document.getElementById('prop-rotacao-label').textContent = `${el.rotacao}°`;

        // Texto
        const grupoTexto = document.getElementById('grupo-texto');
        if (el.tipo === 'texto' || (el.tipo === 'variavel' && el.variavel !== '{foto}')) {
            grupoTexto.classList.remove('hidden');
            document.getElementById('prop-conteudo').value = el.variavel ? el.variavel : (el.conteudo || '');
            document.getElementById('prop-font-size').value = parseInt(el.estilos.fontSize) || 14;
            document.getElementById('prop-font-weight').value = el.estilos.fontWeight || '600';
            document.getElementById('prop-color').value = el.estilos.color || '#1e293b';
            setAlignVisual(el.estilos.textAlign || 'center');
        } else {
            grupoTexto.classList.add('hidden');
        }

        // Fundo/borda
        document.getElementById('prop-opacity').value = el.estilos.opacity !== undefined ? el.estilos.opacity : 100;
        document.getElementById('prop-bg-color').value = el.estilos.backgroundColor || '#6366f1';
        document.getElementById('prop-bg-transparent').checked = !!el.estilos.bgTransparent;
        document.getElementById('prop-border-color').value = el.estilos.borderColor || '#8b5cf6';
        document.getElementById('prop-border-width').value = parseInt(el.estilos.borderWidth) || 0;
        const bRadius = parseInt(el.estilos.borderRadius) || 0;
        document.getElementById('prop-border-radius').value = bRadius;
        document.getElementById('prop-border-radius-label').textContent = `${bRadius}px`;

        // Formato Foto
        const grupoFoto = document.getElementById('grupo-foto-formato');
        if (el.variavel === '{foto}') {
            grupoFoto.classList.remove('hidden');
            let val = 'retangulo';
            if (el.estilos.borderRadius === '50%') {
                val = 'circulo';
            } else if (el.estilos.borderRadius && el.estilos.borderRadius !== '0px') {
                val = 'arredondado';
            }
            document.getElementById('prop-foto-formato').value = val;
        } else {
            grupoFoto.classList.add('hidden');
        }
    }

    function atualizarPainelPosicao(el) {
        document.getElementById('prop-x').value = el.x;
        document.getElementById('prop-y').value = el.y;
        document.getElementById('prop-w').value = el.largura;
        document.getElementById('prop-h').value = el.altura;
    }

    // =====================================================
    // APLICAR PROPRIEDADES AO ELEMENTO
    // =====================================================
    function aplicarPosicao() {
        const el = getElementoData(elementoSelecionadoId);
        if (!el) { return; }
        el.x = parseInt(document.getElementById('prop-x').value) || 0;
        el.y = parseInt(document.getElementById('prop-y').value) || 0;
        const dom = document.getElementById(el.id);
        if (dom) {
            dom.style.transform = `translate(${el.x}px, ${el.y}px) rotate(${el.rotacao}deg)`;
            updateMoveable();
        }
    }

    function aplicarTamanho() {
        const el = getElementoData(elementoSelecionadoId);
        if (!el) { return; }
        el.largura = parseInt(document.getElementById('prop-w').value) || 100;
        el.altura = parseInt(document.getElementById('prop-h').value) || 30;
        const dom = document.getElementById(el.id);
        if (dom) {
            dom.style.width = `${el.largura}px`;
            dom.style.height = `${el.altura}px`;
            updateMoveable();
        }
    }

    function aplicarRotacao(val) {
        const el = getElementoData(elementoSelecionadoId);
        if (!el) { return; }
        el.rotacao = parseInt(val);
        document.getElementById('prop-rotacao-label').textContent = `${el.rotacao}°`;
        const dom = document.getElementById(el.id);
        if (dom) {
            dom.style.transform = `translate(${el.x}px, ${el.y}px) rotate(${el.rotacao}deg)`;
            updateMoveable();
        }
    }

    function aplicarConteudo() {
        const el = getElementoData(elementoSelecionadoId);
        if (!el) { return; }
        const novoConteudo = document.getElementById('prop-conteudo').value;
        el.conteudo = novoConteudo;
        const dom = document.getElementById(el.id);
        const span = dom?.querySelector('span');
        if (span) { span.textContent = novoConteudo; }
    }

    function aplicarEstilo() {
        const el = getElementoData(elementoSelecionadoId);
        if (!el) { return; }

        el.estilos.fontSize = document.getElementById('prop-font-size').value + 'px';
        el.estilos.fontWeight = document.getElementById('prop-font-weight').value;
        el.estilos.color = document.getElementById('prop-color').value;
        el.estilos.textAlign = el.estilos.textAlign || 'center';
        el.estilos.opacity = parseInt(document.getElementById('prop-opacity').value);
        el.estilos.bgTransparent = document.getElementById('prop-bg-transparent').checked;
        el.estilos.backgroundColor = document.getElementById('prop-bg-color').value;
        el.estilos.borderColor = document.getElementById('prop-border-color').value;
        el.estilos.borderWidth = document.getElementById('prop-border-width').value;
        el.estilos.borderRadius = document.getElementById('prop-border-radius').value + 'px';

        const dom = document.getElementById(el.id);
        if (dom) {
            aplicarEstilosNoDOM(dom, el);
            updateMoveable();
        }
    }

    function aplicarFormatoFoto(valor) {
        const el = getElementoData(elementoSelecionadoId);
        if (!el || el.variavel !== '{foto}') { return; }

        let radius = '0px';
        if (valor === 'arredondado') {
            radius = '16px';
        } else if (valor === 'circulo') {
            radius = '50%';
        }

        el.estilos.borderRadius = radius;

        // Atualizar o input de border-radius
        const numRadius = radius === '50%' ? 50 : (radius === '16px' ? 16 : 0);
        document.getElementById('prop-border-radius').value = numRadius;
        document.getElementById('prop-border-radius-label').textContent = radius;

        const dom = document.getElementById(el.id);
        if (dom) {
            aplicarEstilosNoDOM(dom, el);
            updateMoveable();
        }
        salvarHistorico();
    }

    function uploadImagemSelecionada(input) {
        const file = input.files[0];
        if (!file) { return; }

        const reader = new FileReader();
        reader.onload = function(e) {
            const base64Src = e.target.result;
            
            const id = `el_${contadorId++}`;
            const el = {
                id,
                tipo: 'imagem',
                variavel: null,
                labelVariavel: null,
                conteudo: base64Src,
                x: Math.round(CANVAS_W / 2 - 60),
                y: Math.round(CANVAS_H / 2 - 60),
                largura: 120,
                altura: 120,
                rotacao: 0,
                estilos: {
                    fontSize: '14px',
                    fontWeight: '600',
                    color: '#1e293b',
                    textAlign: 'center',
                    backgroundColor: 'transparent',
                    bgTransparent: true,
                    borderColor: '#8b5cf6',
                    borderWidth: '0',
                    borderRadius: '0px',
                    opacity: 100,
                },
            };

            elementosData.push(el);
            const dom = criarElementoDOM(el);
            canvas.appendChild(dom);
            selecionarElemento(id);
            salvarHistorico();
            
            input.value = '';
            showToast('Imagem importada com sucesso!', 'success');
        };
        
        reader.onerror = function() {
            showToast('Erro ao ler arquivo de imagem.', 'error');
        };
        
        reader.readAsDataURL(file);
    }

    function setAlign(align) {
        const el = getElementoData(elementoSelecionadoId);
        if (el) {
            el.estilos.textAlign = align;
            const dom = document.getElementById(el.id);
            if (dom) { dom.style.textAlign = align; }
        }
        setAlignVisual(align);
    }

    function setAlignVisual(align) {
        ['left', 'center', 'right'].forEach(a => {
            const btn = document.getElementById(`align-${a}`);
            if (btn) {
                if (a === align) {
                    btn.className = btn.className.replace('bg-slate-700 border-slate-600 text-slate-300', 'bg-violet-700 border-violet-500 text-white');
                } else {
                    btn.className = btn.className.replace('bg-violet-700 border-violet-500 text-white', 'bg-slate-700 border-slate-600 text-slate-300');
                }
            }
        });
    }

    // =====================================================
    // AÇÕES DE ELEMENTOS
    // =====================================================
    function deletarElementoSelecionado() {
        if (!elementoSelecionadoId) { return; }
        const dom = document.getElementById(elementoSelecionadoId);
        if (dom) { dom.remove(); }
        elementosData = elementosData.filter(el => el.id !== elementoSelecionadoId);
        elementoSelecionadoId = null;
        moveableInstance.target = null;
        updateMoveable();
        document.getElementById('painel-props-conteudo').classList.add('hidden');
        document.getElementById('sem-selecao').classList.remove('hidden');
        salvarHistorico();
        showToast('Elemento deletado.', 'error');
    }

    function duplicarElemento() {
        if (!elementoSelecionadoId) { return; }
        const original = getElementoData(elementoSelecionadoId);
        if (!original) { return; }

        const novoId = `el_${contadorId++}`;
        const copia = JSON.parse(JSON.stringify(original));
        copia.id = novoId;
        copia.x = original.x + 15;
        copia.y = original.y + 15;

        elementosData.push(copia);
        const dom = criarElementoDOM(copia);
        canvas.appendChild(dom);
        selecionarElemento(novoId);
        salvarHistorico();
        showToast('Elemento duplicado!', 'success');
    }

    // =====================================================
    // ZOOM
    // =====================================================
    function ajustarZoom(delta) {
        zoomAtual = Math.max(0.3, Math.min(2, zoomAtual + delta));
        zoomWrapper.style.transform = `scale(${zoomAtual})`;
        document.getElementById('zoom-label').textContent = `${Math.round(zoomAtual * 100)}%`;
        if (moveableInstance) {
            moveableInstance.zoom = zoomAtual;
            updateMoveable();
        }
    }

    // =====================================================
    // TABS DA SIDEBAR
    // =====================================================
    function mudarTab(tab) {
        const painelElem = document.getElementById('painel-elementos');
        const painelProps = document.getElementById('painel-propriedades');
        const tabElem = document.getElementById('tab-elementos');
        const tabProps = document.getElementById('tab-propriedades');

        if (tab === 'elementos') {
            painelElem.classList.remove('hidden');
            painelProps.classList.add('hidden');
            tabElem.className = tabElem.className.replace('border-transparent text-slate-500', 'border-violet-500 text-violet-400');
            tabProps.className = tabProps.className.replace('border-violet-500 text-violet-400', 'border-transparent text-slate-500');
        } else {
            painelElem.classList.add('hidden');
            painelProps.classList.remove('hidden');
            tabProps.className = tabProps.className.replace('border-transparent text-slate-500', 'border-violet-500 text-violet-400');
            tabElem.className = tabElem.className.replace('border-violet-500 text-violet-400', 'border-transparent text-slate-500');
        }
    }

    // =====================================================
    // HISTÓRICO (UNDO/REDO)
    // =====================================================
    function salvarHistorico() {
        const estado = {
            elementos: JSON.parse(JSON.stringify(elementosData)),
            fundo: canvas.style.background,
        };
        // Remover futuros se existirem
        historico = historico.slice(0, historicoIndex + 1);
        historico.push(estado);
        historicoIndex = historico.length - 1;
        // Limitar histórico a 50 estados
        if (historico.length > 50) {
            historico.shift();
            historicoIndex--;
        }
    }

    function undo() {
        if (historicoIndex <= 0) { showToast('Nada para desfazer.', 'info'); return; }
        historicoIndex--;
        restaurarEstado(historico[historicoIndex]);
    }

    function redo() {
        if (historicoIndex >= historico.length - 1) { showToast('Nada para refazer.', 'info'); return; }
        historicoIndex++;
        restaurarEstado(historico[historicoIndex]);
    }

    function restaurarEstado(estado) {
        // Limpa canvas
        canvas.querySelectorAll('.cracha-elemento').forEach(el => el.remove());
        canvas.style.background = estado.fundo;
        document.getElementById('cor-fundo').value = rgbToHex(estado.fundo) || '#ffffff';

        // Recriar elementos
        elementosData = JSON.parse(JSON.stringify(estado.elementos));
        elementosData.forEach(el => {
            const dom = criarElementoDOM(el);
            canvas.appendChild(dom);
        });

        // Desselecionar
        elementoSelecionadoId = null;
        moveableInstance.target = null;
        updateMoveable();
        document.getElementById('painel-props-conteudo').classList.add('hidden');
        document.getElementById('sem-selecao').classList.remove('hidden');
    }

    // =====================================================
    // SALVAR NO BACKEND
    // =====================================================
    function salvarLayout() {
        const payload = {
            dados_json: {
                fundo: canvas.style.background || '#ffffff',
                elementos: elementosData,
            }
        };

        fetch(SAVE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
            },
            body: JSON.stringify(payload),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
            } else {
                showToast('Falha ao salvar: ' + (data.message || 'Erro desconhecido.'), 'error');
            }
        })
        .catch(err => {
            console.error('Erro ao salvar:', err);
            showToast('Erro de rede ao salvar o template.', 'error');
        });
    }

    // =====================================================
    // UTILIDADES
    // =====================================================
    function getElementoData(id) {
        return elementosData.find(el => el.id === id) || null;
    }

    function rgbToHex(rgb) {
        if (!rgb || rgb.startsWith('#')) { return rgb; }
        const result = rgb.match(/\d+/g);
        if (!result || result.length < 3) { return null; }
        return '#' + result.slice(0, 3).map(n => parseInt(n).toString(16).padStart(2, '0')).join('');
    }

    function showToast(message, tipo = 'success') {
        const area = document.getElementById('toast-area');
        const toast = document.createElement('div');

        const bgMap = {
            success: 'bg-emerald-600 border-emerald-500 text-white',
            error: 'bg-rose-700 border-rose-600 text-white',
            info: 'bg-slate-700 border-slate-600 text-slate-200',
        };
        const iconMap = {
            success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>',
            error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>',
            info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        };

        toast.className = `flex items-center gap-2 px-4 py-2.5 rounded-xl border text-sm font-semibold shadow-2xl transition-all duration-300 ${bgMap[tipo] || bgMap.info}`;
        toast.innerHTML = `<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">${iconMap[tipo]}</svg><span>${message}</span>`;
        area.appendChild(toast);

        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
    }
    </script>
</body>
</html>
