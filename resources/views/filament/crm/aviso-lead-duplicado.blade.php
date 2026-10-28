@props(['duplicados' => collect(), 'leadAtual' => null])

@if($duplicados->isNotEmpty())
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50/70 p-4 shadow-xs dark:border-amber-600/40 dark:bg-amber-950/30">
        <div class="flex items-start gap-3">
            <div class="shrink-0 text-amber-600 dark:text-amber-400">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                        Atenção: {{ $duplicados->count() }} possível(is) lead(s) duplicado(s) detectado(s) para este contato
                    </h3>
                    <span class="inline-flex items-center rounded-md bg-amber-200 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-800/80 dark:text-amber-100">
                        {{ $duplicados->count() }} correspondência(s)
                    </span>
                </div>
                <p class="mt-1 text-xs text-amber-800/90 dark:text-amber-300/80">
                    Foram encontrados outros registros cadastrados com o mesmo telefone, CPF, e-mail ou aluno dependente. Unifique as fichas sem perder visitas nem documentos pelo botão abaixo ou pelo menu <strong>Mais</strong> (três pontos) no topo da página.
                </p>

                @if(auth()->user()?->can('Update:Interessado'))
                    <div class="mt-2.5">
                        <button
                            type="button"
                            wire:click="mountAction('mesclarLead')"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-amber-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:bg-amber-500 dark:hover:bg-amber-400 dark:text-gray-950"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                            </svg>
                            <span>Mesclar Duplicados</span>
                        </button>
                    </div>
                @endif

                <div class="mt-3 space-y-2">
                    @foreach($duplicados as $item)
                        @if($item['fora_carteira'] ?? false)
                            <div class="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-100/70 p-2.5 text-xs font-medium text-amber-900 dark:border-amber-700/50 dark:bg-amber-900/40 dark:text-amber-200">
                                <svg class="h-4 w-4 shrink-0 text-amber-700 dark:text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>Há possível duplicado fora da sua carteira. Acione a gestão.</span>
                            </div>
                        @elseif($item['interessado'])
                            @php
                                $outro = $item['interessado'];
                                $motivos = $item['motivos'] ?? [];
                                $detalhes = $item['detalhes'] ?? [];
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-lg border border-amber-200/80 bg-white/80 p-2.5 text-xs dark:border-amber-800/40 dark:bg-gray-900/60">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900 dark:text-gray-100">
                                            Lead #{{ $outro->id }} - {{ $outro->pessoa?->nome ?? 'Sem nome' }}
                                        </span>
                                        @if($outro->status)
                                            <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-white bg-gray-600">
                                                {{ $outro->status->nome }}
                                            </span>
                                        @endif
                                        @if($outro->created_at)
                                            <span class="text-gray-500 dark:text-gray-400 text-[11px]">
                                                (Criado em {{ $outro->created_at->format('d/m/Y') }})
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach($detalhes as $detalhe)
                                            <span class="inline-flex items-center rounded-sm bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                                                {{ $detalhe }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="shrink-0 flex items-center gap-2">
                                    <a href="{{ url('/admin/interessados/' . $outro->id . '/edit') }}" target="_blank" class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                                        <span>Ver lead #{{ $outro->id }}</span>
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
