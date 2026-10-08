<x-filament-panels::page>
    @if ($this->periodoAtivo)
        <div class="mb-5 p-5 bg-gradient-to-r from-primary-900 to-primary-700 text-white rounded-2xl shadow-sm">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                        Campanha Aberta
                    </span>
                    <h2 class="text-lg font-bold mt-2">{{ $this->periodoAtivo->nome }}</h2>
                    <p class="text-xs text-primary-100 mt-1 max-w-xl">
                        {{ $this->periodoAtivo->mensagem_orientacao ?? 'Garanta a vaga do seu filho para o próximo ano letivo com antecedência. Informe a série e o turno de preferência abaixo; a secretaria define a turma e envia o contrato para assinatura.' }}
                    </p>
                </div>
                <div class="text-xs bg-white/10 px-3 py-2 rounded-xl border border-white/20 whitespace-nowrap">
                    <strong>Prazo final:</strong> {{ $this->periodoAtivo->data_fim->format('d/m/Y') }}
                </div>
            </div>
        </div>
    @else
        <div class="mb-5 p-5 bg-amber-50 border border-amber-200 rounded-2xl text-amber-900 dark:bg-amber-950/20 dark:border-amber-800 dark:text-amber-200">
            <div class="flex items-center gap-3">
                <x-heroicon-o-information-circle class="w-6 h-6 text-amber-600 dark:text-amber-400 shrink-0" />
                <div>
                    <h3 class="text-sm font-semibold">Nenhuma Campanha de Rematrícula Aberta no Momento</h3>
                    <p class="text-xs text-amber-800 dark:text-amber-300 mt-0.5">
                        O período de rematrícula para o próximo ano letivo ainda não foi iniciado pela secretaria. Fique atento às comunicações da escola.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
