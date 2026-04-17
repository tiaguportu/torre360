<x-filament-widgets::widget>
    @php
        $status = $this->getQueueStatus();
    @endphp

    <x-filament::section icon="heroicon-o-cpu-chip" icon-color="primary">
        <x-slot name="heading">
            Supervisor de Fila (Queue)
        </x-slot>

        <x-slot name="headerEnd">
            <div class="flex items-center gap-2">
                @if($status['is_running'])
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-success-500"></span>
                    </span>
                    <span class="text-xs font-medium text-success-600">Worker Ativo</span>
                @else
                    <span class="relative flex h-3 w-3">
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-danger-500"></span>
                    </span>
                    <span class="text-xs font-medium text-danger-600">Worker Parado</span>
                @endif
            </div>
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Jobs Pendentes</div>
                <div class="text-2xl font-bold {{ $status['pending'] > 0 ? 'text-warning-600' : 'text-gray-900 dark:text-gray-100' }}">
                    {{ $status['pending'] }}
                </div>
            </div>

            <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Falhas (Failed)</div>
                <div class="text-2xl font-bold {{ $status['failed'] > 0 ? 'text-danger-600' : 'text-gray-900 dark:text-gray-100' }}">
                    {{ $status['failed'] }}
                </div>
            </div>

            <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Última Atividade</div>
                <div class="text-lg font-medium text-gray-900 dark:text-gray-100 italic">
                    {{ $status['last_run'] }}
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            {{ $this->processQueueAction() }}
            
            @if($status['pending'] > 0)
                {{ $this->clearQueueAction() }}
            @endif
        </div>

        @if(!$status['is_running'] && $status['pending'] > 0)
            <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-3">
                <x-filament::icon
                    icon="heroicon-m-information-circle"
                    class="h-5 w-5 text-amber-600 mt-0.5"
                />
                <div class="text-sm text-amber-800">
                    <strong>Atenção:</strong> A fila possui itens pendentes mas o worker parece estar parado. 
                    Clique em <strong>"Processar Fila Agora"</strong> para liberar os processos manualmente.
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
