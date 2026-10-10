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
                    Foram encontrados outros registros cadastrados com o mesmo telefone, CPF, e-mail ou aluno dependente. Use o botão <strong>"Mesclar Duplicados"</strong> no topo da página para unificar as fichas sem perder visitas nem documentos.
                </p>

                <div class="mt-3 space-y-2">
                    @foreach($duplicados as $item)
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
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
