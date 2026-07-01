<div class="space-y-4" 
     x-data="{
        canvas: null,
        state: $wire.entangle('{{ $getStatePath() }}'),
        largura: $wire.entangle('data.largura'),
        altura: $wire.entangle('data.altura'),
        hasSelection: false,
        selectedType: null,
        textColor: '#000000',
        fontSize: 16,
        isBold: false,
        isItalic: false,
        textAlign: 'left',
        
        init() {
            // Garante que o Fabric.js esteja carregado
            if (typeof fabric === 'undefined') {
                let script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js';
                script.onload = () => this.setupCanvas();
                document.head.appendChild(script);
            } else {
                this.setupCanvas();
            }
        },
        
        setupCanvas() {
            this.canvas = new fabric.Canvas('cracha-fabric-canvas', {
                width: parseInt(this.largura) || 300,
                height: parseInt(this.altura) || 480,
                backgroundColor: '#ffffff'
            });
            
            // Carrega layout existente se houver
            if (this.state) {
                let layoutData = typeof this.state === 'string' ? JSON.parse(this.state) : this.state;
                // Previne erros se o layoutData for inválido ou vazio
                if (layoutData && typeof layoutData === 'object') {
                    this.canvas.loadFromJSON(layoutData, () => {
                        this.canvas.renderAll();
                    });
                }
            }
            
            // Eventos de alteração
            const updateState = () => {
                this.state = this.canvas.toJSON();
            };
            
            this.canvas.on('object:added', updateState);
            this.canvas.on('object:modified', updateState);
            this.canvas.on('object:removed', updateState);
            
            // Eventos de seleção
            this.canvas.on('selection:created', (e) => this.handleSelection(e.target));
            this.canvas.on('selection:updated', (e) => this.handleSelection(e.target));
            this.canvas.on('selection:cleared', () => this.clearSelection());
            
            // Watchers para largura/altura
            this.$watch('largura', (val) => {
                let w = parseInt(val) || 300;
                this.canvas.setWidth(w);
                this.canvas.renderAll();
                updateState();
            });
            
            this.$watch('altura', (val) => {
                let h = parseInt(val) || 480;
                this.canvas.setHeight(h);
                this.canvas.renderAll();
                updateState();
            });
        },
        
        addText(textStr, isVariable = false) {
            let options = {
                left: 50,
                top: 50,
                fontFamily: 'sans-serif',
                fontSize: isVariable ? 18 : 16,
                fill: isVariable ? '#1e3a8a' : '#000000',
                fontWeight: isVariable ? 'bold' : 'normal',
                fontStyle: 'normal',
                textAlign: 'left',
                width: 200,
                splitByGrapheme: false
            };
            
            let text = new fabric.IText(textStr, options);
            this.canvas.add(text);
            this.canvas.setActiveObject(text);
            this.canvas.renderAll();
        },
        
        setBackground(e) {
            let file = e.target.files[0];
            if (!file) return;
            
            let reader = new FileReader();
            reader.onload = (f) => {
                let data = f.target.result;
                fabric.Image.fromURL(data, (img) => {
                    img.set({
                        scaleX: this.canvas.width / img.width,
                        scaleY: this.canvas.height / img.height
                    });
                    this.canvas.setBackgroundImage(img, this.canvas.renderAll.bind(this.canvas));
                    this.canvas.renderAll();
                    this.state = this.canvas.toJSON();
                });
            };
            reader.readAsDataURL(file);
        },
        
        removeBackground() {
            this.canvas.setBackgroundImage(null, this.canvas.renderAll.bind(this.canvas));
            this.canvas.renderAll();
            this.state = this.canvas.toJSON();
        },
        
        handleSelection(obj) {
            this.hasSelection = true;
            this.selectedType = obj.type;
            
            if (obj.type === 'text' || obj.type === 'i-text') {
                this.textColor = obj.fill;
                this.fontSize = obj.fontSize;
                this.isBold = obj.fontWeight === 'bold';
                this.isItalic = obj.fontStyle === 'italic';
                this.textAlign = obj.textAlign;
            }
        },
        
        clearSelection() {
            this.hasSelection = false;
            this.selectedType = null;
        },
        
        updateSelectedStyle(property, value) {
            let obj = this.canvas.getActiveObject();
            if (!obj) return;
            
            if (property === 'bold') {
                this.isBold = !this.isBold;
                obj.set('fontWeight', this.isBold ? 'bold' : 'normal');
            } else if (property === 'italic') {
                this.isItalic = !this.isItalic;
                obj.set('fontStyle', this.isItalic ? 'italic' : 'normal');
            } else {
                obj.set(property, value);
                if (property === 'fill') this.textColor = value;
                if (property === 'fontSize') this.fontSize = parseInt(value);
                if (property === 'textAlign') this.textAlign = value;
            }
            
            this.canvas.renderAll();
            this.state = this.canvas.toJSON();
        },
        
        deleteSelected() {
            let obj = this.canvas.getActiveObject();
            if (!obj) return;
            this.canvas.remove(obj);
            this.canvas.discardActiveObject();
            this.canvas.renderAll();
        }
     }"
     x-init="init()">
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Barra de Ferramentas / Controles -->
        <div class="space-y-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm lg:col-span-1">
            <!-- Adicionar Elementos -->
            <div class="space-y-3">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Adicionar Elementos</h3>
                <button type="button" @click="addText('Texto Customizado')"
                        class="w-full inline-flex justify-center items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg text-sm transition shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Inserir Texto Livre
                </button>
            </div>

            <!-- Variáveis de Pessoa -->
            <div class="space-y-2">
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Variáveis de Pessoa</h3>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="addText('{nome}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate">
                        🏷️ Nome Completo
                    </button>
                    <button type="button" @click="addText('{profissao}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate">
                        💼 Profissão
                    </button>
                    <button type="button" @click="addText('{cpf}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate">
                        📄 CPF
                    </button>
                    <button type="button" @click="addText('{identidade}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate">
                        🆔 Identidade (RG)
                    </button>
                    <button type="button" @click="addText('{email}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate">
                        ✉️ E-mail
                    </button>
                    <button type="button" @click="addText('{telefone}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate">
                        📞 Telefone
                    </button>
                    <button type="button" @click="addText('{data_nascimento}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate">
                        📅 Nascimento
                    </button>
                    <button type="button" @click="addText('{sexo}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate">
                        🚻 Sexo
                    </button>
                    <button type="button" @click="addText('{cor_raca}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate col-span-2">
                        🎨 Cor / Raça
                    </button>
                </div>
            </div>

            <!-- Imagem de Fundo -->
            <div class="space-y-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Fundo do Crachá</h3>
                <div class="flex space-x-2">
                    <label class="flex-1 inline-flex justify-center items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-750 dark:text-gray-200 text-sm font-semibold rounded-lg cursor-pointer transition">
                        <span>Carregar Fundo</span>
                        <input type="file" accept="image/*" @change="setBackground($event)" class="hidden">
                    </label>
                    <button type="button" @click="removeBackground()" class="px-3 py-2 bg-red-50 hover:bg-red-100 text-red-650 dark:bg-red-950/20 dark:hover:bg-red-900/30 dark:text-red-400 text-sm font-semibold rounded-lg transition">
                        Limpar
                    </button>
                </div>
            </div>

            <!-- Formatação do Elemento Selecionado -->
            <div x-show="hasSelection" x-transition class="space-y-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-bold text-gray-750 dark:text-gray-200 uppercase tracking-wider">Formatação de Texto</h3>
                
                <div class="grid grid-cols-2 gap-4">
                    <!-- Cor do Texto -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Cor</label>
                        <input type="color" :value="textColor" @input="updateSelectedStyle('fill', $event.target.value)" 
                               class="w-full h-9 rounded cursor-pointer border border-gray-200 dark:border-gray-700 bg-transparent p-0">
                    </div>
                    
                    <!-- Tamanho da Fonte -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Fonte (px)</label>
                        <input type="number" :value="fontSize" min="8" max="72" @input="updateSelectedStyle('fontSize', $event.target.value)"
                               class="w-full h-9 px-2 rounded border border-gray-250 dark:border-gray-700 bg-transparent text-sm dark:text-white">
                    </div>
                </div>

                <!-- Estilos Rápidos -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">Estilos</label>
                    <div class="flex space-x-1">
                        <!-- Bold -->
                        <button type="button" @click="updateSelectedStyle('bold')" 
                                :class="isBold ? 'bg-primary-650 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                class="flex-1 py-1.5 font-bold rounded text-sm transition">
                            B
                        </button>
                        <!-- Italic -->
                        <button type="button" @click="updateSelectedStyle('italic')"
                                :class="isItalic ? 'bg-primary-650 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                                class="flex-1 py-1.5 italic font-semibold rounded text-sm transition">
                            I
                        </button>
                    </div>
                </div>

                <!-- Alinhamento -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">Alinhamento</label>
                    <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 rounded p-1">
                        <button type="button" @click="updateSelectedStyle('textAlign', 'left')"
                                :class="textAlign === 'left' ? 'bg-white dark:bg-gray-700 text-primary-600 shadow-xs' : 'text-gray-500 dark:text-gray-400'"
                                class="flex-1 py-1 text-xs font-semibold rounded transition text-center">
                            Esquerda
                        </button>
                        <button type="button" @click="updateSelectedStyle('textAlign', 'center')"
                                :class="textAlign === 'center' ? 'bg-white dark:bg-gray-700 text-primary-600 shadow-xs' : 'text-gray-500 dark:text-gray-400'"
                                class="flex-1 py-1 text-xs font-semibold rounded transition text-center">
                            Centro
                        </button>
                        <button type="button" @click="updateSelectedStyle('textAlign', 'right')"
                                :class="textAlign === 'right' ? 'bg-white dark:bg-gray-700 text-primary-600 shadow-xs' : 'text-gray-500 dark:text-gray-400'"
                                class="flex-1 py-1 text-xs font-semibold rounded transition text-center">
                            Direita
                        </button>
                    </div>
                </div>

                <!-- Excluir Elemento -->
                <button type="button" @click="deleteSelected()" 
                        class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg text-sm transition shadow-sm">
                    🗑️ Excluir Selecionado
                </button>
            </div>
        </div>

        <!-- Área de Edição (Canvas) -->
        <div class="lg:col-span-2 flex flex-col items-center justify-center border border-gray-200 dark:border-gray-800 rounded-xl p-6 bg-gray-50 dark:bg-gray-950 shadow-inner min-h-[520px]">
            <div class="relative shadow-2xl border border-gray-300 dark:border-gray-700 rounded overflow-hidden" 
                 style="background-image: radial-gradient(circle, #cbcbcb 10%, transparent 11%), radial-gradient(circle, #cbcbcb 10%, #ffffff 11%); background-size: 16px 16px; background-position: 0 0, 8px 8px;">
                <canvas id="cracha-fabric-canvas"></canvas>
            </div>
            <p class="text-xs text-gray-455 mt-4 text-center">Use as bordas e pontos dos objetos selecionados para redimensionar ou mover os textos no crachá.</p>
        </div>
    </div>
</div>
