<x-filament-panels::page>
    <div class="mb-4 p-4 bg-primary-50 border border-primary-200 rounded-xl dark:bg-primary-950/20 dark:border-primary-800">
        <div class="flex items-start gap-3">
            <x-heroicon-o-shield-check class="w-6 h-6 text-primary-600 dark:text-primary-400 shrink-0 mt-0.5" />
            <div>
                <h3 class="text-sm font-semibold text-primary-900 dark:text-primary-100">Documentos Oficiais com Validação Digital por QR Code</h3>
                <p class="text-xs text-primary-700 dark:text-primary-300 mt-1">
                    Todas as certidões e declarações emitidas nesta página contam com carimbo de autenticidade rastreável. Terceiros, órgãos públicos ou empresas podem conferir a veracidade do documento simplesmente apontando a câmera do celular para o QR Code impresso no rodapé.
                </p>
            </div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
