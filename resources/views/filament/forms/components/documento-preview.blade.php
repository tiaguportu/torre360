@php
    $state = $getState();
    
    // O fileupload component geralmente armazena array (ex: ['hash' => 'path']) quando single
    $path = is_array($state) ? (count($state) > 0 ? array_values($state)[0] : null) : $state;
    
    if (!$path) return;
    
    // Se for temporary uploaded file, o path não funciona direto no asset('storage')
    // Precisaria de lógica pro TemporaryUploadedFile, mas como queremos resolver pelo menos os gravados:
    if($path instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
        $url = $path->temporaryUrl();
        $extension = strtolower($path->getClientOriginalExtension());
    } else {
        $url = asset('storage/' . $path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    $isPdf = $extension === 'pdf';
@endphp

<div class="mt-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white/50 dark:bg-gray-800/50 backdrop-blur-md shadow-lg overflow-hidden filament-documento-preview">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
            @if($isImage)
                <x-heroicon-o-photo class="w-5 h-5 text-primary-500" /> Prévia da Imagem
            @elseif($isPdf)
                <x-heroicon-o-document-text class="w-5 h-5 text-primary-500" /> Prévia do PDF
            @else
                <x-heroicon-o-document class="w-5 h-5 text-gray-500" /> Visualização do Documento
            @endif
        </h3>
        <a href="{{ $url }}" target="_blank" class="text-xs px-3 py-1 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-600 hover:bg-primary-200 dark:hover:bg-primary-800 transition-colors font-medium">
            Abrir em nova aba
        </a>
    </div>

    <div class="flex justify-center bg-gray-50 dark:bg-gray-900/50 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
        @if($isImage)
            <img src="{{ $url }}" class="max-h-[600px] w-auto shadow-sm object-contain" alt="Prévia">
        @elseif($isPdf)
            <iframe src="{{ $url }}#toolbar=0" width="100%" height="600px" class="rounded border-none"></iframe>
        @else
            <div class="flex flex-col items-center justify-center p-12 text-gray-400">
                <x-heroicon-o-eye-slash class="w-16 h-16 mb-2 opacity-50" />
                <p class="text-sm">Prévia não disponível para arquivos .{{ $extension }}</p>
            </div>
        @endif
    </div>
</div>
