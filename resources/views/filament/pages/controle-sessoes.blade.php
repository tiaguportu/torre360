<x-filament-panels::page>
    @php($resumo = $this->getResumo())

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $resumo['eh_admin'] ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }}">
        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-primary-50 dark:bg-primary-950 text-primary-600 dark:text-primary-400 rounded-lg">
                    <x-filament::icon icon="heroicon-o-device-phone-mobile" class="w-6 h-6" />
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Minhas Sessões Ativas</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $resumo['total_minhas_sessoes'] }}</div>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                @if($resumo['outros_dispositivos'] > 0)
                    <span class="text-warning-600 dark:text-warning-400 font-semibold">{{ $resumo['outros_dispositivos'] }} outro(s) dispositivo(s)</span> conectado(s) além deste.
                @else
                    <span class="text-success-600 dark:text-success-400 font-semibold">Apenas este dispositivo</span> está conectado no momento.
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-success-50 dark:bg-success-950 text-success-600 dark:text-success-400 rounded-lg">
                    <x-filament::icon icon="heroicon-o-computer-desktop" class="w-6 h-6" />
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dispositivo Atual</div>
                    <div class="text-sm font-bold text-gray-900 dark:text-white truncate" title="{{ $resumo['dispositivo_atual'] }}">{{ $resumo['dispositivo_atual'] }}</div>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Endereço IP: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $resumo['ip_atual'] }}</span>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-info-50 dark:bg-info-950 text-info-600 dark:text-info-400 rounded-lg">
                    <x-filament::icon icon="heroicon-o-shield-check" class="w-6 h-6" />
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Proteção de Acesso</div>
                    <div class="text-sm font-semibold text-success-600 dark:text-success-400">Revogação Ativa</div>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Sessões revogadas são encerradas imediatamente no banco de dados.
            </div>
        </x-filament::section>

        @if($resumo['eh_admin'])
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-warning-50 dark:bg-warning-950 text-warning-600 dark:text-warning-400 rounded-lg">
                        <x-filament::icon icon="heroicon-o-users" class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Escola / Geral</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $resumo['usuarios_conectados'] }} <span class="text-sm font-normal text-gray-500">usuários</span></div>
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Total de <span class="font-semibold">{{ $resumo['total_sessoes_sistema'] }}</span> sessões abertas no sistema.
                </div>
            </x-filament::section>
        @endif
    </div>

    <div class="mt-4">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
