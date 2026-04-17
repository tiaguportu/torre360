@php
    $state = $getState();
    $record = $getRecord();
    
    // Tratamento para TemporaryUploadedFile (novo upload)
    $path = is_array($state) ? (count($state) > 0 ? array_values($state)[0] : null) : $state;
    
    // Fallback: Tenta obter do model salvo caso o state venha nulo ou formato inesperado
    if (!$path && $record && $record->arquivo_path) {
        $path = $record->arquivo_path;
    }
    
    if (!$path) {
        // Se realmente não encontrar nada, exibe esse bloco temporário de debug
        echo '<div style="color:red; padding: 10px; border: 1px solid red; margin-top: 10px; border-radius: 8px;">
            <b>DEBUG DO SISTEMA:</b><br>
            Componente de prévia está renderizando, mas nenhum arquivo foi detectado nas variáveis.<br>
            Estado do campo: ' . json_encode($state) . '<br>
            Caminho do Banco: ' . ($record ? $record->arquivo_path : 'Registro nulo') . '
        </div>';
        return;
    }
    
    if($path instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
        $url = $path->temporaryUrl();
        $extension = strtolower($path->getClientOriginalExtension());
    } else {
        $url = route('documentos.visualizar', ['path' => $path]);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    $isPdf = $extension === 'pdf';
@endphp
<x-filament::section class="filament-documento-preview">
    <x-slot name="heading">
        <div class="flex items-center gap-2">
            @if($isImage)
                <x-filament::icon
                    icon="heroicon-m-photo"
                    class="h-5 w-5 text-primary-500"
                />
                <span>Prévia da Imagem</span>
            @elseif($isPdf)
                <x-filament::icon
                    icon="heroicon-m-document-text"
                    class="h-5 w-5 text-primary-500"
                />
                <span>Prévia do PDF</span>
            @else
                <x-filament::icon
                    icon="heroicon-m-document"
                    class="h-5 w-5 text-gray-500"
                />
                <span>Visualização do Documento</span>
            @endif
        </div>
    </x-slot>

    <x-slot name="headerEnd">
        <x-filament::button
            color="gray"
            icon="heroicon-m-arrow-top-right-on-square"
            icon-position="after"
            tag="a"
            href="{{ $url }}"
            target="_blank"
            size="sm"
            variant="ghost"
        >
            Abrir em nova aba
        </x-filament::button>
    </x-slot>

    <div class="flex justify-center bg-gray-50 dark:bg-gray-900/50 rounded-xl overflow-hidden border border-gray-100 dark:border-gray-800 p-2 min-h-[300px]">
        @if($isImage)
            <img 
                src="{{ $url }}" 
                class="max-h-[600px] w-auto shadow-sm rounded-lg object-contain transition-transform hover:scale-[1.02]" 
                alt="Prévia"
            >
        @elseif($isPdf)
            <iframe 
                src="{{ $url }}#toolbar=0" 
                width="100%" 
                height="600px" 
                class="rounded-lg border-none bg-white shadow-inner"
            ></iframe>
        @else
            <div class="flex flex-col items-center justify-center p-12 text-gray-400">
                <x-filament::icon
                    icon="heroicon-m-eye-slash"
                    class="w-16 h-16 mb-4 opacity-50 text-gray-400"
                />
                <p class="text-base font-medium">Prévia não disponível para arquivos .{{ $extension }}</p>
                <p class="text-sm">Clique no botão acima para tentar abrir o arquivo manualmente.</p>
            </div>
        @endif
    </div>
</x-filament::section>
