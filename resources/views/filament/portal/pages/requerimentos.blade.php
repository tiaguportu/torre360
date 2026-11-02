<x-filament-panels::page>
    <div class="space-y-6">
        @if(! $this->tipoAtual)
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($this->tipos as $tipo)
                    <button type="button" wire:click="escolher('{{ $tipo->chave }}')"
                        class="text-left bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 hover:border-primary-500 transition">
                        <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">{{ $tipo->nome }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($tipo->orientacao, 140) }}</p>
                        <p class="mt-3 text-xs font-semibold text-gray-500 dark:text-gray-400">
                            Resposta em até {{ $tipo->prazoDias }} {{ $tipo->diasUteis ? 'dias úteis' : 'dias' }}
                        </p>
                    </button>
                @endforeach

                <a href="{{ \App\Filament\Portal\Pages\SolicitacoesDocumentos::getUrl(panel: 'portal') }}"
                    class="block bg-white dark:bg-gray-800 rounded-2xl border border-dashed border-gray-300 dark:border-gray-600 p-5 hover:border-primary-500 transition">
                    <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">Declarações e histórico escolar</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">A segunda via de declaração e de histórico continua em "Solicitações de documentos".</p>
                </a>
            </div>
        @else
            @php $tipo = $this->tipoAtual; @endphp
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 max-w-3xl">
                <button type="button" wire:click="voltar" class="text-sm text-primary-600 hover:underline mb-3">&larr; Voltar aos tipos de pedido</button>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $tipo->nome }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $tipo->orientacao }}</p>

                <form wire:submit.prevent="enviar" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            Aluno {{ $tipo->exigeMatricula ? '*' : '(se o pedido for sobre um aluno)' }}
                        </label>
                        <select wire:model="matriculaId" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">{{ $tipo->exigeMatricula ? 'Selecione o aluno...' : 'Nenhum / sobre mim' }}</option>
                            @foreach($this->matriculasAcessiveis as $m)
                                <option value="{{ $m->id }}">{{ $m->pessoa?->nome }} ({{ $m->turma?->nome }})</option>
                            @endforeach
                        </select>
                        @error('matriculaId') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    @foreach($tipo->campos as $campo)
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                                {{ $campo['rotulo'] }} {{ ($campo['obrigatorio'] ?? false) ? '*' : '' }}
                            </label>

                            @if($campo['tipo'] === 'textarea')
                                <textarea wire:model="campos.{{ $campo['nome'] }}" rows="3"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"></textarea>
                            @elseif($campo['tipo'] === 'select')
                                <select wire:model="campos.{{ $campo['nome'] }}" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                                    <option value="">Selecione...</option>
                                    @foreach($campo['opcoes'] as $valor => $rotulo)
                                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                            @elseif($campo['tipo'] === 'file')
                                <input type="file" wire:model="anexo" accept=".pdf,.jpg,.jpeg,.png,.webp"
                                    class="w-full text-sm text-gray-700 dark:text-gray-300" />
                                @error('anexo') <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                            @else
                                <input type="{{ $campo['tipo'] === 'date' ? 'date' : 'text' }}" wire:model="campos.{{ $campo['nome'] }}"
                                    class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
                            @endif

                            @error('campos.'.$campo['nome']) <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    @endforeach

                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white font-bold text-sm">
                        Enviar pedido
                    </button>
                </form>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-base">Meus pedidos</h3>
            </div>

            @if($this->meusPedidos->isEmpty())
                <p class="p-6 text-sm text-gray-500 dark:text-gray-400">Você ainda não fez nenhum pedido por aqui.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($this->meusPedidos as $pedido)
                        <li class="p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <span class="font-mono text-xs font-bold bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">{{ $pedido->protocolo }}</span>
                                <span class="ml-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $pedido->tipo()?->nome ?? $pedido->assunto }}</span>
                                @if($pedido->matricula)
                                    <span class="text-xs text-gray-500">— {{ $pedido->matricula->pessoa?->nome }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400 sm:text-right">
                                @if($pedido->resultado)
                                    <strong class="{{ $pedido->resultado->value === 'atendido' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $pedido->resultado->getLabel() }}</strong>
                                @else
                                    {{ $pedido->status->getLabel() }}
                                    @if($pedido->prazo_resolucao_em) · previsão até {{ $pedido->prazo_resolucao_em->format('d/m/Y') }} @endif
                                @endif
                                <a href="{{ \App\Filament\Portal\Pages\CentralAtendimento::getUrl(panel: 'portal') }}" class="ml-2 text-primary-600 hover:underline">Ver conversa</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-filament-panels::page>
