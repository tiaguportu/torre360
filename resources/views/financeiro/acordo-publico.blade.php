<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termo de Acordo e Renegociação Escolar #{{ $acordo->codigo }} - Torre360</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <!-- Logo e Cabeçalho -->
        <div class="text-center mb-8">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                🔒 Ambiente Seguro de Negociação Financeira
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-3">
                Proposta de Acordo e Regularização
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Colégio Torre360 • Acordo nº <strong class="text-slate-700">{{ $acordo->codigo }}</strong>
            </p>
        </div>

        @if(session('mensagem_sucesso'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-3">
                <svg class="w-5 h-5 text-emerald-600 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <div>
                    <strong class="font-semibold block">Acordo Formalizado!</strong>
                    {{ session('mensagem_sucesso') }}
                </div>
            </div>
        @endif

        @if(session('mensagem_erro'))
            <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <strong class="font-semibold block">Aviso</strong>
                    {{ session('mensagem_erro') }}
                </div>
            </div>
        @endif

        <!-- Cartão Principal de Resumo -->
        <div class="bg-white shadow-sm border border-slate-200 rounded-2xl overflow-hidden mb-8">
            <div class="p-6 sm:p-8 border-b border-slate-100">
                <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                    <div>
                        <span class="text-xs uppercase font-bold tracking-wider text-slate-400">Responsável Financeiro</span>
                        <h2 class="text-lg font-bold text-slate-900">{{ $acordo->responsavelPessoa?->nome ?? 'Responsável' }}</h2>
                        <p class="text-xs text-slate-500">Estudante: {{ $acordo->matricula?->pessoa?->nome ?? 'Aluno' }}</p>
                    </div>
                    <div class="sm:text-right">
                        <span class="text-xs uppercase font-bold tracking-wider text-slate-400">Situação do Acordo</span>
                        <div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                {{ $acordo->status->value === 'ativo' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $acordo->status->value === 'cumprido' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                {{ $acordo->status->value === 'aguardando_aceite' ? 'bg-amber-100 text-amber-800' : '' }}
                                {{ $acordo->status->value === 'simulado' ? 'bg-slate-100 text-slate-800' : '' }}
                                {{ $acordo->status->value === 'quebrado' ? 'bg-red-100 text-red-800' : '' }}">
                                {{ $acordo->status->getLabel() }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Destaque dos Valores -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 pt-6 border-t border-slate-100 text-center sm:text-left">
                    <div class="p-4 rounded-xl bg-slate-50">
                        <span class="text-xs text-slate-500">Valor Original da Pendência</span>
                        <div class="text-base font-semibold text-slate-700 line-through">
                            R$ {{ number_format((float) $acordo->valor_original_total, 2, ',', '.') }}
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-emerald-50/70 border border-emerald-100">
                        <span class="text-xs text-emerald-700 font-medium">Desconto Especial Concedido</span>
                        <div class="text-base font-bold text-emerald-700">
                            - R$ {{ number_format((float) $acordo->valor_desconto, 2, ',', '.') }}
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-primary-50/70 border border-primary-100">
                        <span class="text-xs text-primary-700 font-medium">Valor Total Consolidado</span>
                        <div class="text-xl font-extrabold text-primary-700">
                            R$ {{ number_format((float) $acordo->valor_total_acordo, 2, ',', '.') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cronograma de Pagamentos -->
            <div class="p-6 sm:p-8 bg-slate-50/50">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 mb-4">
                    Plano de Pagamento Prazos e Parcelas ({{ $acordo->quantidade_parcelas }}x)
                </h3>
                <div class="space-y-3">
                    @foreach($acordo->parcelas as $parcela)
                        <div class="flex items-center justify-between p-3.5 bg-white border border-slate-200 rounded-xl text-sm">
                            <div class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs {{ $parcela->numero_parcela === 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $parcela->numero_parcela === 0 ? 'Ent.' : $parcela->numero_parcela.'ª' }}
                                </span>
                                <div>
                                    <span class="font-medium text-slate-800">
                                        {{ $parcela->numero_parcela === 0 ? 'Entrada Inicial' : 'Parcela '.$parcela->numero_parcela.' de '.$acordo->quantidade_parcelas }}
                                    </span>
                                    <span class="block text-xs text-slate-500">
                                        Vencimento: {{ $parcela->data_vencimento->format('d/m/Y') }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-slate-900 block">
                                    R$ {{ number_format((float) $parcela->valor, 2, ',', '.') }}
                                </span>
                                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded {{ $parcela->status === 'pago' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $parcela->status === 'pago' ? '✓ Pago' : 'Pendente' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Área de Aceite e Termo Legal -->
            @if(in_array($acordo->status->value, ['aguardando_aceite', 'simulado']))
                <div class="p-6 sm:p-8 border-t border-slate-200 bg-white">
                    <h3 class="text-sm font-bold text-slate-900 mb-2">Termo de Confissão de Dívida e Transação</h3>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 max-h-48 overflow-y-auto mb-6 whitespace-pre-line leading-relaxed font-mono">
                        {{ $termo }}
                    </div>

                    <form action="{{ route('acordo.publico.aceitar', ['token' => $acordo->token_publico]) }}" method="POST">
                        @csrf
                        <label class="flex items-start gap-3 cursor-pointer text-xs text-slate-700 mb-6">
                            <input type="checkbox" name="concordo" required class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500 h-4 w-4">
                            <span>
                                Li, concordo expressamente com as condições e valores estipulados e formalizo o presente <strong>Instrumento Particular de Confissão e Transação de Dívida</strong> com eficácia de Título Executivo Extrajudicial (Art. 784, III do CPC).
                            </span>
                        </label>

                        <button type="submit" class="w-full sm:w-auto px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-sm transition-all focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 flex items-center justify-center gap-2">
                            <span>Confirmar e Assinar Acordo Digitalmente</span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </button>
                    </form>
                </div>
            @elseif($acordo->aceito_em)
                <div class="p-6 border-t border-slate-200 bg-emerald-50/40 text-xs text-emerald-800">
                    <span class="font-bold block">✓ Acordo Formalizado Eletronicamente</span>
                    Aceite registrado em {{ $acordo->aceito_em->format('d/m/Y \à\s H:i:s') }} (IP: {{ $acordo->ip_aceite ?? 'registrado' }}).
                </div>
            @endif
        </div>

        <div class="text-center text-xs text-slate-400">
            Dúvidas? Entre em contato com a secretaria do Colégio Torre360.
        </div>
    </div>
</body>
</html>
