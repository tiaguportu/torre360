<div
    x-data="{
        isUploading: false,
        isDropping: false,
        progress: 0,
    }"
    class="relative group"
>
    <div
        x-on:dragover.prevent="isDropping = true"
        x-on:dragleave.prevent="isDropping = false"
        x-on:drop.prevent="
            isDropping = false;
            if ($event.dataTransfer.files.length > 0) {
                const file = $event.dataTransfer.files[0];
                
                // Validação básica de tipo de arquivo (PDF ou Imagem)
                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    window.newNotification = {
                        title: 'Tipo de arquivo não permitido',
                        body: 'Por favor, envie apenas PDF ou Imagens.',
                        status: 'danger'
                    };
                    // Disparar notificação do Filament via JS se possível, ou deixar o backend validar.
                    // Por enquanto, apenas vamos tentar o upload.
                }

                isUploading = true;
                $wire.upload('manualFileUpload', file, (uploadedFilename) => {
                    $wire.processManualUpload('{{ $getRecord()->id }}', uploadedFilename, file.name);
                    isUploading = false;
                    progress = 0;
                }, () => {
                    isUploading = false;
                    progress = 0;
                }, (event) => {
                    progress = event.detail.progress;
                });
            }
        "
        :class="{
            'border-warning-500 bg-warning-50 dark:bg-warning-900/10': isDropping,
            'border-gray-300 dark:border-gray-700': !isDropping
        }"
        class="flex flex-col items-center justify-center p-2 transition-all border-2 border-dashed rounded-lg cursor-pointer hover:border-warning-500 hover:bg-warning-50 dark:hover:bg-warning-900/10"
        title="Arraste um arquivo PDF ou Imagem para enviar"
    >
        <template x-if="!isUploading">
            <div class="flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                <x-filament::icon
                    icon="heroicon-o-cloud-arrow-up"
                    class="w-5 h-5 text-gray-400 group-hover:text-warning-500 transition-colors"
                />
                <span class="group-hover:text-warning-600 transition-colors">Arraste aqui</span>
            </div>
        </template>

        <template x-if="isUploading">
            <div class="w-full">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] font-medium text-warning-600 animate-pulse">Enviando...</span>
                    <span class="text-[10px] font-medium text-warning-600 font-mono" x-text="progress + '%'"></span>
                </div>
                <div class="w-full h-1.5 bg-gray-200 rounded-full dark:bg-gray-700 overflow-hidden">
                    <div
                        class="h-full transition-all duration-300 bg-warning-600 rounded-full shadow-[0_0_8px_rgba(var(--warning-600),0.5)]"
                        :style="'width: ' + progress + '%'"
                    ></div>
                </div>
            </div>
        </template>
    </div>
</div>
