<style>
    /* Impede que o Tailwind e CSS global do Filament apliquem max-width ou redimensionamento inadequado nos canvas do Fabric.js */
    #canvas-container-wrapper .canvas-container,
    #canvas-container-wrapper canvas {
        max-width: none !important;
        max-height: none !important;
    }
</style>

<div class="space-y-4" wire:ignore
     x-data="{
        canvas: null,
        state: $wire.entangle('{{ $getStatePath() }}'),
        largura: $wire.entangle('data.largura'),
        altura: $wire.entangle('data.altura'),
        hasSelection: false,
        selectedType: null,
        isTextSelected: false,
        objLeft: 0,
        objTop: 0,
        objWidth: 0,
        objHeight: 0,
        textColor: '#000000',
        fontSize: 16,
        isBold: false,
        isItalic: false,
        textAlign: 'left',
        fontFamily: 'sans-serif',
        
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
            // Se já existir uma instância do canvas, limpa tudo de forma limpa para evitar vazamento de memória e elementos fantasma
            if (this.canvas) {
                try {
                    this.canvas.dispose();
                } catch (e) {
                    console.error('Erro ao destruir canvas antigo:', e);
                }
                this.canvas = null;
            }

            // Limpa o contêiner DOM e recria a tag canvas limpa antes de instanciar o Fabric
            const wrapper = document.getElementById('canvas-container-wrapper');
            if (wrapper) {
                wrapper.innerHTML = '<canvas id=\'cracha-fabric-canvas\'></canvas>';
            }
            
            this.canvas = new fabric.Canvas('cracha-fabric-canvas', {
                width: parseInt(this.largura) || 300,
                height: parseInt(this.altura) || 480,
                backgroundColor: '#ffffff'
            });
            
            // Força a visibilidade dos controles de seleção e redimensionamento do Fabric
            fabric.Object.prototype.transparentCorners = false;
            fabric.Object.prototype.cornerColor = '#3b82f6';
            fabric.Object.prototype.borderColor = '#3b82f6';
            fabric.Object.prototype.cornerSize = 12;
            fabric.Object.prototype.padding = 6;
            fabric.Object.prototype.cornerStyle = 'circle';
            fabric.Object.prototype.hasControls = true;
            
            // Carrega layout existente se houver
            if (this.state) {
                let layoutData = typeof this.state === 'string' ? JSON.parse(this.state) : this.state;
                if (layoutData && typeof layoutData === 'object') {
                    this.canvas.loadFromJSON(layoutData, () => {
                        // Força todos os objetos a recalcularem suas coordenadas de alça de controle após o carregamento
                        this.canvas.getObjects().forEach(obj => obj.setCoords());
                        this.canvas.renderAll();
                    });
                }
            }
            
            // Eventos de alteração
            const updateState = () => {
                this.state = this.canvas.toJSON(['id']);
            };
            this.updateState = updateState;
            
            this.canvas.on('object:added', updateState);
            this.canvas.on('object:modified', updateState);
            this.canvas.on('object:removed', updateState);
            
            // Eventos de seleção
            this.canvas.on('selection:created', (e) => this.handleSelection(e.target));
            this.canvas.on('selection:updated', (e) => this.handleSelection(e.target));
            this.canvas.on('selection:cleared', () => this.clearSelection());
            
            // Eventos de movimento e redimensionamento para atualizar painel numérico
            this.canvas.on('object:moving', (e) => this.updateGeometryPanel(e.target));
            this.canvas.on('object:scaling', (e) => this.updateGeometryPanel(e.target));
            
            // Ouvintes de drag and drop diretamente no upperCanvasEl do Fabric.js
            const upperCanvas = this.canvas.upperCanvasEl;
            if (upperCanvas) {
                upperCanvas.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'copy';
                });

                upperCanvas.addEventListener('drop', (e) => {
                    e.preventDefault();
                    let itemType = e.dataTransfer.getData('text/plain');
                    if (!itemType) return;
                    
                    let rect = upperCanvas.getBoundingClientRect();
                    let x = e.clientX - rect.left;
                    let y = e.clientY - rect.top;
                    
                    if (itemType === '{foto}') {
                        this.addPhotoPlaceholder(x, y);
                    } else {
                        this.addText(itemType, itemType.startsWith('{'), x, y);
                    }
                });
            }
            
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
        
        addText(textStr, isVariable = false, x = 50, y = 50) {
            let options = {
                left: x,
                top: y,
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
            text.setCoords();
            this.canvas.renderAll();
        },

        addPhotoPlaceholder(x = 50, y = 50) {
            let placeholder = new fabric.Rect({
                left: 0,
                top: 0,
                width: 100,
                height: 100,
                fill: '#e5e7eb',
                stroke: '#9ca3af',
                strokeWidth: 2,
                strokeDashArray: [5, 5]
            });
            
            // Texto para indicar que é uma foto no editor
            let label = new fabric.Text('FOTO', {
                fontSize: 16,
                fill: '#6b7280',
                fontWeight: 'bold',
                originX: 'center',
                originY: 'center',
                left: 50,
                top: 50
            });
            
            let group = new fabric.Group([placeholder, label], {
                left: x,
                top: y,
                id: 'foto_group'
            });

            // Adiciona propriedade customizada id no grupo principal para ser detectado no render do PDF
            group.id = 'foto';

            this.canvas.add(group);
            this.canvas.setActiveObject(group);
            group.setCoords();
            this.canvas.renderAll();
        },
        
        dragStart(e, itemType) {
            e.dataTransfer.setData('text/plain', itemType);
            e.dataTransfer.effectAllowed = 'copy';
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
                    this.updateState();
                });
            };
            reader.readAsDataURL(file);
        },
        
        removeBackground() {
            this.canvas.setBackgroundImage(null, this.canvas.renderAll.bind(this.canvas));
            this.canvas.renderAll();
            this.updateState();
        },
        
        handleSelection(obj) {
            this.hasSelection = true;
            this.selectedType = obj.type;
            
            const isText = obj.type === 'text' || 
                           obj.type === 'i-text' || 
                           obj.type === 'textbox' || 
                           (obj && typeof obj.text === 'string');
            
            this.isTextSelected = isText;
            
            if (isText) {
                this.textColor = obj.fill;
                this.fontSize = obj.fontSize;
                this.isBold = obj.fontWeight === 'bold';
                this.isItalic = obj.fontStyle === 'italic';
                this.textAlign = obj.textAlign;
                this.fontFamily = obj.fontFamily || 'sans-serif';
            }
            
            try {
                this.updateGeometryPanel(obj);
            } catch (e) {
                console.warn('Erro ao atualizar geometria:', e);
            }
        },
        
        clearSelection() {
            this.hasSelection = false;
            this.selectedType = null;
            this.isTextSelected = false;
            this.objLeft = 0;
            this.objTop = 0;
            this.objWidth = 0;
            this.objHeight = 0;
        },
        
        updateGeometryPanel(obj) {
            if (!obj) return;
            this.objLeft = Math.round(obj.left || 0);
            this.objTop = Math.round(obj.top || 0);
            this.objWidth = Math.round((obj.width || 0) * (obj.scaleX || 1));
            this.objHeight = Math.round((obj.height || 0) * (obj.scaleY || 1));
        },
        
        applyGeometry(property, value) {
            let obj = this.canvas.getActiveObject();
            if (!obj) return;
            
            let val = parseInt(value);
            if (isNaN(val)) return;
            
            if (property === 'left' || property === 'top') {
                obj.set(property, val);
            } else if (property === 'width') {
                let scaleX = val / (obj.width || 1);
                obj.set('scaleX', scaleX);
            } else if (property === 'height') {
                let scaleY = val / (obj.height || 1);
                obj.set('scaleY', scaleY);
            }
            
            obj.setCoords();
            this.canvas.renderAll();
            this.updateState();
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
                if (property === 'fontFamily') this.fontFamily = value;
            }
            
            this.canvas.renderAll();
            this.updateState();
        },

        bringToFront() {
            let obj = this.canvas.getActiveObject();
            if (obj) {
                this.canvas.bringToFront(obj);
                this.canvas.renderAll();
                this.updateState();
            }
        },

        sendToBack() {
            let obj = this.canvas.getActiveObject();
            if (obj) {
                this.canvas.sendToBack(obj);
                this.canvas.renderAll();
                this.updateState();
            }
        },

        centerHorizontally() {
            let obj = this.canvas.getActiveObject();
            if (obj) {
                obj.centerH();
                obj.setCoords();
                this.canvas.renderAll();
                this.updateState();
            }
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
        <div class="space-y-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm lg:col-span-1 max-h-[800px] overflow-y-auto">
            
            <!-- Dica de arrastar e soltar -->
            <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-3 text-sm text-blue-700 dark:text-blue-300">
                <p class="font-medium">💡 Dica:</p>
                <p>Você pode <strong>clicar</strong> nos botões abaixo ou <strong>arrastá-los</strong> para o crachá.</p>
            </div>

            <!-- Adicionar Elementos -->
            <div class="space-y-3">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Adicionar Elementos</h3>
                <div role="button" draggable="true" @dragstart="dragStart($event, 'Texto Customizado')" @click="addText('Texto Customizado')"
                        class="w-full inline-flex justify-center items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg text-sm transition shadow-sm cursor-grab active:cursor-grabbing select-none text-center font-semibold">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Inserir Texto Livre
                </div>
            </div>

            <!-- Variáveis de Pessoa -->
            <div class="space-y-2">
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Variáveis de Pessoa</h3>
                <div class="grid grid-cols-2 gap-2">
                    <div role="button" draggable="true" @dragstart="dragStart($event, '{foto}')" @click="addPhotoPlaceholder()" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate cursor-grab col-span-2 border border-dashed border-gray-300 dark:border-gray-600 select-none">
                        🖼️ Foto da Pessoa
                    </div>
                    <div role="button" draggable="true" @dragstart="dragStart($event, '{nome}')" @click="addText('{nome}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate cursor-grab select-none">
                        🏷️ Nome Completo
                    </div>
                    <div role="button" draggable="true" @dragstart="dragStart($event, '{profissao}')" @click="addText('{profissao}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate cursor-grab select-none">
                        💼 Profissão
                    </div>
                    <div role="button" draggable="true" @dragstart="dragStart($event, '{cpf}')" @click="addText('{cpf}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate cursor-grab select-none">
                        📄 CPF
                    </div>
                    <div role="button" draggable="true" @dragstart="dragStart($event, '{identidade}')" @click="addText('{identidade}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate cursor-grab select-none">
                        🆔 Identidade (RG)
                    </div>
                    <div role="button" draggable="true" @dragstart="dragStart($event, '{email}')" @click="addText('{email}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate cursor-grab select-none">
                        ✉️ E-mail
                    </div>
                    <div role="button" draggable="true" @dragstart="dragStart($event, '{telefone}')" @click="addText('{telefone}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate cursor-grab select-none">
                        📞 Telefone
                    </div>
                    <div role="button" draggable="true" @dragstart="dragStart($event, '{data_nascimento}')" @click="addText('{data_nascimento}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate cursor-grab select-none">
                        📅 Nascimento
                    </div>
                    <div role="button" draggable="true" @dragstart="dragStart($event, '{sexo}')" @click="addText('{sexo}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate cursor-grab select-none">
                        🚻 Sexo
                    </div>
                    <div role="button" draggable="true" @dragstart="dragStart($event, '{cor_raca}')" @click="addText('{cor_raca}', true)" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition text-left truncate cursor-grab col-span-2 select-none">
                        🎨 Cor / Raça
                    </div>
                </div>
            </div>

            <!-- Imagem de Fundo -->
            <div class="space-y-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Fundo do Crachá</h3>
                <div class="flex space-x-2">
                    <label class="flex-1 inline-flex justify-center items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-750 text-gray-750 dark:text-gray-200 text-sm font-semibold rounded-lg cursor-pointer transition select-none">
                        <span>Carregar Fundo</span>
                        <input type="file" accept="image/*" @change="setBackground($event)" class="hidden">
                    </label>
                    <button type="button" @click="removeBackground()" class="px-3 py-2 bg-red-50 hover:bg-red-100 text-red-650 dark:bg-red-950/20 dark:hover:bg-red-900/30 dark:text-red-400 text-sm font-semibold rounded-lg transition select-none">
                        Limpar
                    </button>
                </div>
            </div>

            <!-- Formatação do Elemento Selecionado -->
            <div x-show="hasSelection" x-transition class="space-y-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-bold text-gray-750 dark:text-gray-200 uppercase tracking-wider">Objeto Selecionado</h3>
                
                <!-- Dimensões Numéricas (Para qualquer objeto) -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">Posição & Dimensões (Geometria)</label>
                    <div class="grid grid-cols-4 gap-2 border-b border-gray-100 dark:border-gray-800 pb-4">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">X (px)</label>
                            <input type="number" x-model="objLeft" @change="applyGeometry('left', $event.target.value)" class="w-full h-8 px-2 rounded border border-gray-250 text-xs text-center dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Y (px)</label>
                            <input type="number" x-model="objTop" @change="applyGeometry('top', $event.target.value)" class="w-full h-8 px-2 rounded border border-gray-250 text-xs text-center dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Largura</label>
                            <input type="number" x-model="objWidth" @change="applyGeometry('width', $event.target.value)" class="w-full h-8 px-2 rounded border border-gray-250 text-xs text-center dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Altura</label>
                            <input type="number" x-model="objHeight" @change="applyGeometry('height', $event.target.value)" class="w-full h-8 px-2 rounded border border-gray-250 text-xs text-center dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
                        </div>
                    </div>
                </div>
                
                <div x-show="isTextSelected || selectedType === 'text' || selectedType === 'i-text' || selectedType === 'textbox'">
                    <div class="space-y-4">
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

                        <!-- Família da Fonte -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Família da Fonte</label>
                            <select :value="fontFamily" @change="updateSelectedStyle('fontFamily', $event.target.value)"
                                    class="w-full h-9 px-2 rounded border border-gray-255 dark:border-gray-700 bg-transparent text-sm dark:text-white dark:bg-gray-900">
                                <option value="sans-serif">Sans-Serif (Padrão)</option>
                                <option value="Arial">Arial</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Courier New">Courier New</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Verdana">Verdana</option>
                                <option value="Impact">Impact</option>
                            </select>
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

                        <!-- Alinhamento de Texto -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-2">Alinhar Texto</label>
                            <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 rounded p-1">
                                <button type="button" @click="updateSelectedStyle('textAlign', 'left')"
                                        :class="textAlign === 'left' ? 'bg-white dark:bg-gray-700 text-primary-600 shadow-xs' : 'text-gray-500 dark:text-gray-400'"
                                        class="flex-1 py-1 text-xs font-semibold rounded transition text-center select-none">
                                    Esq.
                                </button>
                                <button type="button" @click="updateSelectedStyle('textAlign', 'center')"
                                        :class="textAlign === 'center' ? 'bg-white dark:bg-gray-700 text-primary-600 shadow-xs' : 'text-gray-500 dark:text-gray-400'"
                                        class="flex-1 py-1 text-xs font-semibold rounded transition text-center select-none">
                                    Centro
                                </button>
                                <button type="button" @click="updateSelectedStyle('textAlign', 'right')"
                                        :class="textAlign === 'right' ? 'bg-white dark:bg-gray-700 text-primary-600 shadow-xs' : 'text-gray-500 dark:text-gray-400'"
                                        class="flex-1 py-1 text-xs font-semibold rounded transition text-center select-none">
                                    Dir.
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Posição e Profundidade (Para qualquer objeto) -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-2">Alinhamento & Profundidade</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="bringToFront()" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition select-none">
                            ⬆️ Trazer p/ Frente
                        </button>
                        <button type="button" @click="sendToBack()" class="px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition select-none">
                            ⬇️ Enviar p/ Trás
                        </button>
                        <button type="button" @click="centerHorizontally()" class="col-span-2 px-2 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded transition select-none">
                            ↔️ Centralizar Horizontalmente
                        </button>
                    </div>
                </div>

                <!-- Excluir Elemento -->
                <button type="button" @click="deleteSelected()" 
                        class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg text-sm transition shadow-sm mt-4 select-none">
                    🗑️ Excluir Selecionado
                </button>
            </div>
        </div>

        <!-- Área de Edição (Canvas) -->
        <div class="lg:col-span-2 flex flex-col items-center justify-center border border-gray-200 dark:border-gray-800 rounded-xl p-6 bg-gray-50 dark:bg-gray-950 shadow-inner min-h-[520px]">
            <div id="canvas-container-wrapper" wire:ignore class="relative shadow-2xl border border-gray-300 dark:border-gray-700 rounded overflow-hidden" 
                 style="background-image: radial-gradient(circle, #cbcbcb 10%, transparent 11%), radial-gradient(circle, #cbcbcb 10%, #ffffff 11%); background-size: 16px 16px; background-position: 0 0, 8px 8px;">
                <canvas id="cracha-fabric-canvas"></canvas>
            </div>
            <p class="text-xs text-gray-455 mt-4 text-center select-none">Arraste e solte os botões no crachá ou clique neles para adicioná-los. Use as bordas dos objetos para redimensionar ou mover.</p>
        </div>
    </div>
</div>
