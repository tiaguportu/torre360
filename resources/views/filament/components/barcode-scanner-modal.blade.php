@once
    <script src="{{ asset('js/html5-qrcode.min.js') }}"></script>
    <style>
        @keyframes scanner-laser {
            0% { top: 8%; opacity: 0.8; }
            50% { top: 92%; opacity: 1; }
            100% { top: 8%; opacity: 0.8; }
        }
        .animate-scanner-laser {
            position: absolute;
            left: 5%;
            right: 5%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #ef4444, #f87171, #ef4444, transparent);
            box-shadow: 0 0 8px #ef4444;
            animation: scanner-laser 2s ease-in-out infinite;
        }
        #barcode-reader-container video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 0.75rem !important;
        }
    </style>
@endonce

<div
    x-data="barcodeCameraScanner()"
    x-on:abrir-scanner-camera.window="abrirScanner($event.detail.contexto, $event.detail.titulo, $event.detail.subtitulo)"
    x-on:keydown.escape.window="if(aberto) fecharScanner()"
    class="relative z-50"
>
    {{-- INPUT OCULTO PARA CAPTURA NATIVA DE FOTO (SEMPRE FUNCIONA, INCLUSIVE EM HTTP DE REDE LOCAL) --}}
    <input
        type="file"
        accept="image/*"
        capture="environment"
        class="hidden"
        x-ref="inputFotoCamera"
        @change="processarFotoArquivo($event)"
    />

    {{-- OVERLAY / MODAL --}}
    <div
        x-show="aberto"
        x-cloak
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-950/75 backdrop-blur-xs flex items-center justify-center p-4 z-50"
    >
        <div
            x-show="aberto"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-800 w-full max-w-md overflow-hidden flex flex-col"
            @click.away="fecharScanner()"
        >
            {{-- CABEÇALHO DO MODAL --}}
            <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-850/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg bg-primary-100 dark:bg-primary-950 text-primary-600 dark:text-primary-400 flex items-center justify-center font-bold shadow-xs">
                        <x-heroicon-o-camera class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="titulo"></h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400" x-text="subtitulo"></p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="fecharScanner()"
                    class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    title="Fechar Câmera"
                >
                    <x-heroicon-m-x-mark class="w-5 h-5" />
                </button>
            </div>

            {{-- CORPO: VISOR DA CÂMERA & MENSAGENS --}}
            <div class="p-4 flex flex-col items-center">

                {{-- FEEDBACK DE LEITURA BEM-SUCEDIDA --}}
                <div
                    x-show="feedbackSucesso"
                    x-transition
                    class="w-full p-2.5 mb-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs flex items-center gap-2 font-medium"
                >
                    <x-heroicon-m-check-circle class="w-4 h-4 text-emerald-600" />
                    <span>Lido: <strong class="font-mono text-emerald-900 dark:text-emerald-100" x-text="ultimoCodigoLido"></strong></span>
                </div>

                {{-- MENSAGEM QUANDO O NAVEGADOR BLOQUEIA VÍDEO POR HTTP (INSECURE CONTEXT) OU PERMISSÃO --}}
                <template x-if="erroCamera">
                    <div class="w-full p-3.5 mb-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200 text-xs space-y-2">
                        <div class="font-bold flex items-center gap-1.5 text-amber-800 dark:text-amber-300">
                            <x-heroicon-m-exclamation-triangle class="w-4 h-4 text-amber-600 shrink-0" />
                            <span x-text="tituloErro"></span>
                        </div>
                        <p class="text-[11px] leading-relaxed text-amber-800/90 dark:text-amber-300/90" x-text="erroCamera"></p>

                        {{-- BOTÃO DE AÇÃO RÁPIDA: TIRAR FOTO COM A CÂMERA DO CELULAR (FUNCIONA 100% EM QUALQUER CONEXÃO) --}}
                        <div class="pt-1">
                            <button
                                type="button"
                                @click="tirarFotoNativa()"
                                class="w-full py-2.5 px-3 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold text-xs flex items-center justify-center gap-2 shadow-xs transition"
                            >
                                <x-heroicon-m-camera class="w-4 h-4" />
                                <span>Tirar Foto do Código com a Câmera</span>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- VISOR DA CÂMERA AO VIVO COM RETÂNGULO DE MIRA E LINHA DE LASER --}}
                <div
                    x-show="!insecureContextDetected && (!erroCamera || cameraRodando)"
                    class="relative w-full aspect-4/3 bg-black rounded-xl overflow-hidden border border-gray-300 dark:border-gray-700 shadow-inner flex items-center justify-center"
                >
                    <div id="barcode-reader-container" class="w-full h-full"></div>

                    {{-- MOLDURA DE MIRA VISUAL --}}
                    <div x-show="!erroCamera && cameraRodando" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                        <div class="relative w-[75%] h-[55%] border-2 border-primary-500/80 rounded-xl shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]">
                            <div class="animate-scanner-laser"></div>
                            {{-- CANTOS DA MIRA --}}
                            <div class="absolute -top-1 -left-1 w-3 h-3 border-t-2 border-l-2 border-primary-400"></div>
                            <div class="absolute -top-1 -right-1 w-3 h-3 border-t-2 border-r-2 border-primary-400"></div>
                            <div class="absolute -bottom-1 -left-1 w-3 h-3 border-b-2 border-l-2 border-primary-400"></div>
                            <div class="absolute -bottom-1 -right-1 w-3 h-3 border-b-2 border-r-2 border-primary-400"></div>
                        </div>
                    </div>

                    {{-- SPINNER CARREGANDO CÂMERA --}}
                    <div x-show="iniciando" class="absolute inset-0 bg-black/60 flex flex-col items-center justify-center text-white gap-2">
                        <svg class="animate-spin h-7 w-7 text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        <span class="text-xs font-medium">Iniciando câmera...</span>
                    </div>
                </div>

                {{-- SPINNER PROCESSANDO FOTO --}}
                <div x-show="processandoFoto" class="w-full py-4 flex flex-col items-center justify-center gap-2 text-primary-600 dark:text-primary-400">
                    <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span class="text-xs font-medium">Decodificando código de barras da foto...</span>
                </div>

                {{-- CONTROLES INFERIORES: BOTAO DE FOTO DIRETO & MODO CONTÍNUO --}}
                <div class="w-full mt-3 flex flex-col sm:flex-row items-center justify-between gap-2.5 text-xs">
                    {{-- BOTÃO ALTERNATIVO DE FOTO SEMPRE VISÍVEL --}}
                    <button
                        type="button"
                        @click="tirarFotoNativa()"
                        class="text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 flex items-center gap-1.5 underline decoration-dotted"
                    >
                        <x-heroicon-m-camera class="w-3.5 h-3.5" />
                        <span>Capturar por foto (câmera nativa)</span>
                    </button>

                    {{-- TOGGLE LEITURA CONTÍNUA --}}
                    <label class="flex items-center gap-2 cursor-pointer select-none text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            x-model="leituraContinua"
                            class="rounded text-primary-600 focus:ring-primary-500 dark:bg-gray-800 border-gray-300 dark:border-gray-700"
                        />
                        <span class="text-[11px] font-semibold">Leitura contínua</span>
                    </label>
                </div>
            </div>

            {{-- RODAPÉ DO MODAL --}}
            <div class="p-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-850/50 flex justify-between items-center text-xs">
                <span class="text-[11px] text-gray-500">Suporta Code 128 (tombo) e ISBN/EAN-13.</span>
                <button
                    type="button"
                    @click="fecharScanner()"
                    class="px-3.5 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white font-medium transition"
                >
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function barcodeCameraScanner() {
        return {
            aberto: false,
            contexto: 'emprestimo',
            titulo: 'Escanear Código do Livro',
            subtitulo: 'Aponte a câmera para o código de barras ou ISBN',
            scanner: null,
            iniciando: false,
            cameraRodando: false,
            processandoFoto: false,
            insecureContextDetected: false,
            erroCamera: null,
            tituloErro: 'Acesso à Câmera',
            leituraContinua: false,
            feedbackSucesso: false,
            ultimoCodigoLido: null,
            ultimaTimestampLeitura: 0,

            abrirScanner(contexto, titulo, subtitulo) {
                this.contexto = contexto || 'emprestimo';
                this.titulo = titulo || 'Escanear Código do Livro';
                this.subtitulo = subtitulo || 'Aponte a câmera para o código de barras ou ISBN';
                this.aberto = true;
                this.erroCamera = null;
                this.feedbackSucesso = false;
                this.ultimoCodigoLido = null;
                this.processandoFoto = false;

                // Verifica se está em contexto seguro (HTTPS ou localhost)
                const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname);
                const isHttps = window.location.protocol === 'https:';
                const isSecure = window.isSecureContext || isLocalhost || isHttps;

                if (!isSecure) {
                    this.insecureContextDetected = true;
                    this.tituloErro = 'Conexão HTTP na Rede Local';
                    this.erroCamera = 'O Google Chrome e Safari do celular bloqueiam vídeo ao vivo da câmera em conexões HTTP sem SSL (ex: http://' + window.location.host + '). Para ler o livro sem precisar de HTTPS, use a opção abaixo para fotografar o código:';
                    this.iniciando = false;
                    return;
                }

                this.insecureContextDetected = false;
                this.iniciando = true;

                this.$nextTick(() => {
                    this.iniciarCamera();
                });
            },

            tirarFotoNativa() {
                if (this.$refs && this.$refs.inputFotoCamera) {
                    this.$refs.inputFotoCamera.click();
                }
            },

            async processarFotoArquivo(event) {
                const file = event.target.files && event.target.files[0];
                if (!file) return;

                this.processandoFoto = true;
                this.erroCamera = null;

                try {
                    let scannerInstancia = this.scanner;
                    if (!scannerInstancia) {
                        scannerInstancia = new Html5Qrcode('barcode-reader-container');
                        this.scanner = scannerInstancia;
                    }

                    // Escaneia a imagem tirada com a câmera do celular
                    const decodedText = await scannerInstancia.scanFile(file, true);
                    this.aoLerCodigo(decodedText);
                } catch (err) {
                    this.tituloErro = 'Código não detectado na foto';
                    this.erroCamera = 'Não foi possível ler o código de barras nesta foto. Tente fotografar mais de perto, em foco e com boa iluminação.';
                } finally {
                    this.processandoFoto = false;
                    event.target.value = '';
                }
            },

            fecharScanner() {
                this.pararCamera();
                this.aberto = false;
                this.erroCamera = null;
                this.feedbackSucesso = false;
                this.processandoFoto = false;
            },

            tocarBeep() {
                try {
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (AudioContextClass) {
                        const audioCtx = new AudioContextClass();
                        const osc = audioCtx.createOscillator();
                        const gain = audioCtx.createGain();

                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(1400, audioCtx.currentTime);
                        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.12);

                        osc.connect(gain);
                        gain.connect(audioCtx.destination);

                        osc.start();
                        osc.stop(audioCtx.currentTime + 0.12);
                    }
                } catch (e) {}

                if (navigator.vibrate) {
                    try {
                        navigator.vibrate(100);
                    } catch (e) {}
                }
            },

            async iniciarCamera() {
                if (typeof Html5Qrcode === 'undefined') {
                    this.iniciando = false;
                    this.tituloErro = 'Biblioteca não carregada';
                    this.erroCamera = 'O leitor de código de barras não foi carregado. Recarregue a página.';
                    return;
                }

                // Verifica se navigator.mediaDevices existe no navegador
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    this.iniciando = false;
                    this.tituloErro = 'Câmera não suportada ou bloqueada';
                    this.erroCamera = 'O navegador não permitiu acesso à câmera em tempo real nesta conexão. Utilize a opção de foto abaixo:';
                    return;
                }

                try {
                    const container = document.getElementById('barcode-reader-container');
                    if (!container) return;

                    if (!this.scanner) {
                        this.scanner = new Html5Qrcode('barcode-reader-container');
                    }

                    const config = {
                        fps: 15,
                        qrbox: { width: 280, height: 160 },
                        aspectRatio: 1.333333,
                    };

                    // Tentativa 1: câmera traseira (environment)
                    try {
                        await this.scanner.start(
                            { facingMode: 'environment' },
                            config,
                            (decodedText) => this.aoLerCodigo(decodedText),
                            () => {}
                        );
                    } catch (errFacing) {
                        // Tentativa 2: qualquer câmera disponível
                        await this.scanner.start(
                            {},
                            config,
                            (decodedText) => this.aoLerCodigo(decodedText),
                            () => {}
                        );
                    }

                    this.iniciando = false;
                    this.cameraRodando = true;
                    this.erroCamera = null;
                } catch (err) {
                    this.iniciando = false;
                    this.cameraRodando = false;
                    this.tituloErro = 'Permissão de Câmera Necessária';
                    this.erroCamera = 'Não foi possível iniciar o vídeo ao vivo. Certifique-se de que a permissão de câmera está liberada nas configurações do seu navegador ou utilize o botão abaixo para fotografar o código:';
                }
            },

            async pararCamera() {
                if (this.scanner && this.cameraRodando) {
                    try {
                        await this.scanner.stop();
                    } catch (e) {}
                    this.cameraRodando = false;
                }
            },

            aoLerCodigo(codigo) {
                if (!codigo) return;
                const codigoLimpo = codigo.trim();
                const agora = Date.now();

                if (this.ultimoCodigoLido === codigoLimpo && (agora - this.ultimaTimestampLeitura < 2500)) {
                    return;
                }
                if (agora - this.ultimaTimestampLeitura < 1200) {
                    return;
                }

                this.ultimoCodigoLido = codigoLimpo;
                this.ultimaTimestampLeitura = agora;
                this.feedbackSucesso = true;
                this.tocarBeep();

                if (this.contexto === 'emprestimo') {
                    if (window.Livewire && typeof this.$wire !== 'undefined') {
                        this.$wire.processarLeituraEmprestimo(codigoLimpo);
                    }
                } else if (this.contexto === 'devolucao') {
                    if (window.Livewire && typeof this.$wire !== 'undefined') {
                        this.$wire.processarLeituraDevolucao(codigoLimpo);
                    }
                } else if (this.contexto === 'inventario') {
                    if (window.Livewire && typeof this.$wire !== 'undefined') {
                        this.$wire.processarLeituraInventario(codigoLimpo);
                    }
                } else if (this.contexto === 'sacola_adicionar') {
                    if (window.Livewire && typeof this.$wire !== 'undefined') {
                        this.$wire.processarLeituraCameraAdicionar(codigoLimpo);
                    }
                } else if (this.contexto === 'sacola_devolver') {
                    if (window.Livewire && typeof this.$wire !== 'undefined') {
                        this.$wire.processarLeituraCameraDevolver(codigoLimpo);
                    }
                }

                if (!this.leituraContinua) {
                    setTimeout(() => {
                        this.fecharScanner();
                    }, 500);
                } else {
                    setTimeout(() => {
                        this.feedbackSucesso = false;
                    }, 2000);
                }
            }
        };
    }
</script>
