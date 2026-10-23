{{-- Guia das variáveis de um modelo de documento: $catalogo = grupo => [variável => descrição]. --}}
<div class="grid grid-cols-1 gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-xs md:grid-cols-2 dark:border-slate-800 dark:bg-slate-900/50">
    @foreach ($catalogo as $grupo => $variaveis)
        <div>
            <h5 class="mb-1 font-bold text-slate-800 dark:text-slate-200">{{ $grupo }}:</h5>
            <ul class="space-y-1 font-mono text-slate-600 dark:text-slate-400">
                @foreach ($variaveis as $variavel => $descricao)
                    <li><code>{{ $variavel }}</code> — {{ $descricao }}</li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
<p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
    Escreva a variável exatamente como está acima, com as chaves coladas ao nome. Uma variável escrita errada, ou de outro tipo de documento, é recusada ao salvar.
</p>
