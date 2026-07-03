<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Editor de Crachá V2 - {{ $templateCrachaV2->nome }}</title>
    <!-- Tailwind CSS (usando a versão do projeto ou via CDN para consistência da UI premium) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .glassmorphism {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, 0.5);
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(100, 116, 139, 0.8);
        }
        iframe {
            background: #f1f5f9;
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 flex flex-col h-screen overflow-hidden font-sans">

    <!-- Header Premium -->
    <header class="bg-slate-950 border-b border-slate-800 px-6 py-4 flex items-center justify-between z-10 shadow-lg">
        <div class="flex items-center gap-3">
            <div class="bg-emerald-500 text-slate-950 font-bold p-2 rounded-lg text-sm shadow-md shadow-emerald-500/20">
                CR-V2
            </div>
            <div>
                <h1 class="text-lg font-bold tracking-tight text-white">{{ $templateCrachaV2->nome }}</h1>
                <p class="text-xs text-slate-400">
                    Dimensões: <span class="text-emerald-400 font-semibold">{{ $templateCrachaV2->largura }}x{{ $templateCrachaV2->altura }} px</span> | 
                    Entidade: <span class="text-blue-400 font-semibold capitalize">{{ $templateCrachaV2->tipo_entidade->value ?? $templateCrachaV2->tipo_entidade }}</span>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <!-- Toast de Status -->
            <div id="toast" class="hidden opacity-0 transition-all duration-300 transform translate-y-2 bg-emerald-500 text-slate-950 px-4 py-2 rounded-lg text-sm font-semibold shadow-lg flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span id="toast-message">Salvo com sucesso!</span>
            </div>

            <button onclick="salvarLayout()" class="bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-all duration-200 shadow-md shadow-emerald-600/20 flex items-center gap-2 border border-emerald-500/30">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Salvar Template
            </button>
            <button onclick="window.close()" class="bg-slate-800 hover:bg-slate-700 active:scale-95 text-slate-300 px-5 py-2.5 rounded-lg font-semibold text-sm transition-all duration-200 border border-slate-700">
                Fechar Editor
            </button>
        </div>
    </header>

    <!-- Main Workspace -->
    <div class="flex flex-1 overflow-hidden">
        
        <!-- Sidebar: Injeção de Variáveis -->
        <aside class="w-80 bg-slate-950 border-r border-slate-800 flex flex-col shadow-2xl z-10">
            <!-- Instruções -->
            <div class="p-5 border-b border-slate-800">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-300 mb-2">Editor V2 (Canvas Vetorial)</h2>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Desenhe o crachá usando as ferramentas do SVG-Edit no centro. Use as variáveis abaixo para inserir campos dinâmicos que serão preenchidos automaticamente no PDF.
                </p>
            </div>

            <!-- Lista de Variáveis -->
            <div class="flex-1 overflow-y-auto p-5 space-y-6 custom-scrollbar">
                
                <!-- Variáveis de Pessoa -->
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-emerald-400 mb-3 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Campos de Pessoa
                    </h3>
                    <div class="grid grid-cols-1 gap-2">
                        @foreach ($todasVariaveis['pessoa']['Variáveis de Pessoa'] ?? [] as $key => $label)
                            @php
                                $classVal = trim($key, '{}');
                            @endphp
                            <button onclick="copiarClasse('{{ $classVal }}')" class="bg-slate-900 hover:bg-slate-800 text-left px-3 py-2.5 rounded-lg border border-slate-800 text-xs font-medium transition-all duration-200 hover:border-emerald-500/50 flex items-center justify-between group active:scale-95">
                                <span class="text-slate-200 group-hover:text-emerald-400">{{ $label }}</span>
                                <span class="text-[10px] text-slate-500 font-mono bg-slate-950 px-1.5 py-0.5 rounded group-hover:text-emerald-400">Class: {{ $classVal }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Variáveis de Turma (Se aplicável) -->
                @if ($templateCrachaV2->tipo_entidade->value === 'turma')
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-blue-400 mb-3 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                        Campos de Turma
                    </h3>
                    <div class="grid grid-cols-1 gap-2">
                        @foreach ($todasVariaveis['turma']['Variáveis de Turma'] ?? [] as $key => $label)
                            @php
                                $classVal = trim($key, '{}');
                            @endphp
                            <button onclick="copiarClasse('{{ $classVal }}')" class="bg-slate-900 hover:bg-slate-800 text-left px-3 py-2.5 rounded-lg border border-slate-800 text-xs font-medium transition-all duration-200 hover:border-blue-500/50 flex items-center justify-between group active:scale-95">
                                <span class="text-slate-200 group-hover:text-blue-400">{{ $label }}</span>
                                <span class="text-[10px] text-slate-500 font-mono bg-slate-950 px-1.5 py-0.5 rounded group-hover:text-blue-400">Class: {{ $classVal }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>

            <!-- Rodapé da Sidebar -->
            <div class="p-4 bg-slate-950 border-t border-slate-800 text-center">
                <span class="text-[10px] text-slate-500 font-mono">Torre360 Badge Engine V2</span>
            </div>
        </aside>

        <!-- Canvas Iframe Area -->
        <main class="flex-1 bg-slate-900 p-6 flex items-center justify-center relative overflow-hidden">
            <div class="w-full h-full rounded-xl overflow-hidden border border-slate-800 shadow-2xl relative">
                <!-- Overlay de Carregamento -->
                <div id="editor-loading" class="absolute inset-0 bg-slate-950 flex flex-col items-center justify-center z-20 transition-opacity duration-300">
                    <div class="w-10 h-10 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mb-4"></div>
                    <p class="text-sm font-semibold text-slate-300">Inicializando Editor Canvas...</p>
                    <p class="text-xs text-slate-500 mt-1">Carregando módulos do SVG-Edit</p>
                </div>

                <iframe id="svgedit-iframe" src="{{ asset('vendor/svgedit/editor/index.html') }}?v={{ time() }}" class="w-full h-full border-none"></iframe>
            </div>
        </main>

    </div>

    <!-- Scripts de Integração do SVG-Edit -->
    <!-- Scripts de Integração do SVG-Edit -->
    <script>
        let canvasAPI = null;
        let iframeWindow = null;

        // Dimensões do Template
        const templateWidth = parseInt("{{ $templateCrachaV2->largura }}") || 300;
        const templateHeight = parseInt("{{ $templateCrachaV2->altura }}") || 480;

        // SVG Inicial Padrão se estiver em branco
        @php
            $defaultSvg = '<svg width="' . $templateCrachaV2->largura . '" height="' . $templateCrachaV2->altura . '" viewBox="0 0 ' . $templateCrachaV2->largura . ' ' . $templateCrachaV2->altura . '" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><rect width="100%" height="100%" fill="#ffffff" stroke="#e2e8f0" stroke-width="2" id="canvas-background" /></svg>';
        @endphp
        const initialSvgContent = {!! json_encode($templateCrachaV2->svg_content ?: $defaultSvg) !!};

        // Monitorar carregamento do Iframe
        const iframe = document.getElementById('svgedit-iframe');
        
        // Loop de checagem para encontrar a API do SVG-Edit (svgCanvas ou svgEditor)
        const checkEditorInterval = setInterval(() => {
            try {
                if (iframe && iframe.contentWindow) {
                    const win = iframe.contentWindow;
                    // SVG-Edit 7 expõe svgCanvas na window. Fallback para svgEditor.svgCanvas ou svgEditor.canvas se necessário.
                    const api = win.svgCanvas || (win.svgEditor ? (win.svgEditor.svgCanvas || win.svgEditor.canvas) : null);
                    
                    if (api) {
                        iframeWindow = win;
                        canvasAPI = api;
                        clearInterval(checkEditorInterval);
                        inicializarEditor();
                    }
                }
            } catch (e) {
                // CORS ou ainda carregando
            }
        }, 100);

        function inicializarEditor() {
            try {
                let svgParaCarregar = initialSvgContent;
                // Remove preâmbulo <?xml ...?> se existir para evitar quebras de parser no canvas
                svgParaCarregar = svgParaCarregar.replace(/^<\?xml[^>]*\?>/i, '').trim();

                // Carregar o SVG inicial usando setSvgString ou o método correspondente
                if (typeof canvasAPI.setSvgString === 'function') {
                    canvasAPI.setSvgString(svgParaCarregar);
                } else if (typeof canvasAPI.loadFromString === 'function') {
                    canvasAPI.loadFromString(svgParaCarregar);
                }

                // Configurar o tamanho do canvas de trabalho para corresponder exatamente ao tamanho do crachá
                if (typeof canvasAPI.setResolution === 'function') {
                    canvasAPI.setResolution(templateWidth, templateHeight);
                } else if (canvasAPI.canvas && typeof canvasAPI.canvas.setResolution === 'function') {
                    canvasAPI.canvas.setResolution(templateWidth, templateHeight);
                }

                // Ocultar overlay de carregamento
                const loadingOverlay = document.getElementById('editor-loading');
                if (loadingOverlay) {
                    loadingOverlay.classList.add('opacity-0');
                    setTimeout(() => loadingOverlay.remove(), 300);
                }
            } catch (e) {
                console.error("Erro ao inicializar conteúdo do SVG-Edit:", e);
            }
        }

        /**
         * Copia a classe para a área de transferência do usuário
         */
        function copiarClasse(className) {
            navigator.clipboard.writeText(className).then(() => {
                showToast(`Classe "${className}" copiada para o clipboard! Cole-a na propriedade Class do elemento no SVG-Edit.`, "bg-emerald-500");
            }).catch(err => {
                console.error("Erro ao copiar classe:", err);
                showToast("Erro ao copiar classe para a área de transferência.", "bg-rose-600");
            });
        }

        /**
         * Salva as alterações no banco de dados via AJAX
         */
        function salvarLayout() {
            if (!canvasAPI) {
                alert("Não foi possível salvar: O editor não está pronto.");
                return;
            }

            showToast("Salvando layout...", "bg-slate-700");

            try {
                let svg = '';

                // 1. Tenta obter via svgCanvasToString() (síncrono e oficial do canvas do SVG-Edit 7)
                if (typeof canvasAPI.svgCanvasToString === 'function') {
                    svg = canvasAPI.svgCanvasToString();
                }
                // 2. Tenta obter via getSvgString() direto
                else {
                    const res = canvasAPI.getSvgString();
                    if (typeof res === 'string') {
                        svg = res;
                    } else if (res && typeof res.then === 'function') {
                        res.then(val => {
                            if (typeof val === 'string') {
                                enviarSvgParaBackend(val);
                            }
                        }).catch(err => {
                            console.error("Erro na Promise do SVG:", err);
                        });
                        return;
                    }
                }

                if (svg && svg.trim() !== '') {
                    enviarSvgParaBackend(svg);
                } else {
                    // 3. Fallback assíncrono clássico se getSvgString requerer callback
                    if (typeof canvasAPI.getSvgString === 'function') {
                        canvasAPI.getSvgString()(function(val, error) {
                            if (error) {
                                console.error("Erro no callback do SVG:", error);
                                showToast("Erro ao extrair SVG do editor.", "bg-rose-600");
                                return;
                            }
                            if (val) {
                                enviarSvgParaBackend(val);
                            } else {
                                showToast("Erro: O editor retornou SVG vazio.", "bg-rose-600");
                            }
                        });
                    } else {
                        showToast("Erro: Método de exportação de SVG não encontrado.", "bg-rose-600");
                    }
                }
            } catch (e) {
                console.error("Erro no fluxo de salvamento:", e);
                showToast("Erro ao processar salvamento.", "bg-rose-600");
            }
        }

        function enviarSvgParaBackend(svg) {
            fetch("{{ route('template-crachas-v2.save', $templateCrachaV2->id) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ svg_content: svg })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, "bg-emerald-500");
                } else {
                    showToast("Falha ao salvar: " + (data.message || "Erro desconhecido"), "bg-rose-600");
                }
            })
            .catch(err => {
                console.error("Erro na requisição AJAX:", err);
                showToast("Erro de rede ao salvar o template.", "bg-rose-600");
            });
        }

        /**
         * Exibe um toast premium na interface
         */
        function showToast(message, bgColorClass) {
            const toast = document.getElementById('toast');
            const toastMsg = document.getElementById('toast-message');
            
            // Remove classes de cor anteriores
            toast.className = toast.className.replace(/bg-\w+-\d+/g, '');
            toast.classList.add(bgColorClass);

            toastMsg.textContent = message;
            
            toast.classList.remove('hidden');
            setTimeout(() => {
                toast.classList.remove('opacity-0', 'translate-y-2');
            }, 50);

            // Se for de sucesso, esconde após 3 segundos
            if (bgColorClass === "bg-emerald-500") {
                setTimeout(() => {
                    toast.classList.add('opacity-0', 'translate-y-2');
                    setTimeout(() => {
                        toast.classList.add('hidden');
                    }, 300);
                }, 3000);
            }
        }
    </script>
</body>
</html>
