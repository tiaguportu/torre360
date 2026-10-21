<x-filament-panels::page>
    @php
        $espelho = $this->getEspelho();
        $funcionario = $this->getFuncionarioSelecionado();
        $nomes = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
        $hm = fn (?int $m): string => $m === null ? '—' : ($m < 0 ? '-' : '').sprintf('%02d:%02d', intdiv(abs($m), 60), abs($m) % 60);
        $situacoes = ['ok' => 'Apurado', 'folga' => 'Folga', 'falta' => 'Falta', 'abonado' => 'Abonado', 'incompleto' => 'Incompleto', 'em_andamento' => 'Em andamento', 'futuro' => ''];
    @endphp

    <div class="space-y-4">
        {{ $this->form }}

        @if (! $espelho)
            <x-filament::section>
                <p class="text-sm text-gray-500">Nenhum funcionário selecionado.</p>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">{{ $funcionario->pessoa?->nome }} · {{ sprintf('%02d/%d', $espelho['mes'], $espelho['ano']) }}</x-slot>
                <x-slot name="description">{{ $funcionario->cargo }}@if ($funcionario->unidade) · {{ $funcionario->unidade->nome }}@endif</x-slot>

                <div class="mb-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
                    <div><div class="text-gray-500">Previsto</div><strong>{{ $hm($espelho['totais']['previsto']) }}</strong></div>
                    <div><div class="text-gray-500">Trabalhado</div><strong>{{ $hm($espelho['totais']['trabalhado']) }}</strong></div>
                    <div><div class="text-gray-500">Crédito no banco</div><strong>{{ $hm($espelho['totais']['extras_banco']) }}</strong></div>
                    <div><div class="text-gray-500">Extras a pagar</div><strong>{{ $hm($espelho['totais']['extras_a_pagar']) }}</strong></div>
                    <div><div class="text-gray-500">Débitos</div><strong>{{ $hm($espelho['totais']['debitos']) }}</strong></div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                                <th class="py-2 pr-3">Dia</th><th class="pr-3">Marcações</th><th class="pr-3 text-right">Previsto</th><th class="pr-3 text-right">Trabalhado</th><th class="pr-3 text-right">Saldo</th><th class="pr-3">Situação</th><th>Alertas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($espelho['dias'] as $dia)
                                <tr class="border-b border-gray-100 align-top dark:border-white/5" wire:key="dia-{{ $dia['data'] }}">
                                    <td class="py-2 pr-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($dia['data'])->format('d/m') }} {{ $nomes[$dia['dia_semana']] }}</td>
                                    <td class="pr-3 tabular-nums">{{ collect($dia['marcacoes'])->map(fn ($m) => $m['hora'].($m['ajustada'] ? '*' : ''))->implode(' · ') }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $hm($dia['previsto']) }}</td>
                                    <td class="pr-3 text-right tabular-nums">{{ $hm($dia['trabalhado']) }}</td>
                                    <td class="pr-3 text-right tabular-nums {{ ($dia['saldo'] ?? 0) < 0 ? 'text-danger-600' : '' }}">{{ $hm($dia['saldo']) }}</td>
                                    <td class="pr-3">{{ $situacoes[$dia['situacao']] ?? $dia['situacao'] }}@if ($dia['feriado']) · {{ $dia['feriado'] }}@endif</td>
                                    <td class="text-warning-700">{{ collect($dia['alertas'])->pluck('mensagem')->implode(' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500">* Marcação incluída por ajuste aprovado.</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
