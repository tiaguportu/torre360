<div class="fi-ta-actions flex shrink-0 items-center justify-center gap-3 font-semibold text-sm">
    <div x-data="{
        isUploading: false,
        isDropping: false,
        progress: 0,
    }" class="relative group">
        <div 
            x-on:dragover.prevent="isDropping = true" 
            x-on:dragleave.prevent="isDropping = false" 
            x-on:drop.prevent="
                isDropping = false;
                if ($event.dataTransfer.files.length > 0) {
                    const file = $event.dataTransfer.files[0];
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
            class="flex items-center"
        >
            <template x-if="!isUploading">
                <div 
                    class="fi-color fi-color-warning fi-text-color-700 dark:fi-text-color-400 fi-link fi-size-sm fi-ac-link-action inline-flex items-center gap-1.5 transition-all duration-75 cursor-pointer group px-2 py-1 rounded-md"
                    x-bind:class="{ 'bg-warning-50/50 ring-2 ring-warning-500': isDropping }"
                >
                    <div x-bind:style="isDropping ? 'color: var(--warning-600)' : ''" class="flex items-center gap-1.5">
                        <x-filament::icon 
                            icon="heroicon-o-cloud-arrow-up" 
                            class="fi-icon fi-size-sm transition-colors group-hover:text-warning-600 dark:group-hover:text-warning-400"
                        />
                        <span 
                            class="transition-colors group-hover:text-warning-600 dark:group-hover:text-warning-400"
                            x-text="isDropping ? 'Solte Aqui' : 'Arraste'"
                        ></span>
                    </div>
                </div>
            </template>

            <template x-if="isUploading">
                <div class="flex items-center gap-2 px-2 py-1 min-w-[100px]">
                    <x-filament::loading-indicator class="fi-size-sm text-warning-600" />
                    <div class="flex-1 h-1.5 bg-gray-200 rounded-full dark:bg-gray-700 overflow-hidden relative">
                        <div 
                            class="absolute inset-y-0 left-0 bg-warning-600 transition-all duration-300"
                            x-bind:style="'width: ' + progress + '%'"
                        ></div>
                    </div>
                    <span class="text-[10px] font-mono text-warning-600 whitespace-nowrap" x-text="progress + '%'"></span>
                </div>
            </template>
        </div>
    </div>
</div>