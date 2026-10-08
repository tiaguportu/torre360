<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Portal de Pré-Admissão | Torre360</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo-adaptative.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('icon.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen pb-16">
    <!-- Topbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-3xl mx-auto px-4 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="{{ asset('logo-adaptative.svg') }}" alt="Torre360" class="w-10 h-10 rounded-xl shadow-xs object-contain border border-slate-100 p-0.5 bg-white shrink-0">
                <div>
                    <h1 class="font-bold text-slate-900 text-sm sm:text-base leading-tight">Portal de Pré-Admissão</h1>
                    <p class="text-xs text-slate-500">Matrícula & Checklist Digital de Documentos</p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                    🔒 Ambiente Seguro
                </span>
            </div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 pt-6 space-y-6">
        <!-- Feedback Messages -->
        @if(session('sucesso'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-start gap-3 shadow-xs">
                <span class="text-xl">✅</span>
                <div class="text-sm font-medium">{{ session('sucesso') }}</div>
            </div>
        @endif

        @if(session('erro'))
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-3 shadow-xs">
                <span class="text-xl">⚠️</span>
                <div class="text-sm font-medium">{{ session('erro') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm space-y-1">
                <span class="font-bold block mb-1">Por favor, verifique os campos abaixo:</span>
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $etapaFamiliaConcluida = $etapaFamiliaConcluida ?? false;
        @endphp

        <!-- Banner de Conclusão da Etapa da Família -->
        @if($etapaFamiliaConcluida)
            <div class="p-5 sm:p-6 rounded-2xl bg-gradient-to-br from-emerald-600 via-teal-700 to-emerald-800 text-white shadow-md space-y-4">
                <div class="flex items-start gap-3.5 sm:gap-4">
                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-2xl shrink-0 font-bold border border-white/30">
                        ✓
                    </div>
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-white/20 text-white border border-white/30">
                                Etapa da Família Concluída
                            </span>
                            <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-emerald-950/40 text-emerald-100">
                                ⏳ Aguardando Validação da Secretaria
                            </span>
                        </div>
                        <h2 class="text-base sm:text-xl font-extrabold leading-snug">
                            Tudo pronto por aqui! Sua pré-matrícula foi enviada com sucesso. 🎉
                        </h2>
                        <p class="text-xs sm:text-sm text-emerald-50 leading-relaxed max-w-2xl">
                            Você concluiu o cadastro e o envio de todos os documentos obrigatórios. As abas abaixo permanecem disponíveis para você consultar o que foi enviado (em <strong>modo de somente leitura</strong>).
                        </p>
                    </div>
                </div>

                <div class="pt-3 border-t border-white/20 text-xs">
                    <span class="text-emerald-100 flex items-center gap-1.5">
                        <span>🔒</span> <strong>Modo Somente Leitura:</strong> Formulário e uploads bloqueados para a conferência oficial da secretaria.
                    </span>
                </div>
            </div>
        @endif

        <!-- Card de Identificação e Resumo do Candidato -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <span class="text-xs font-semibold tracking-wider uppercase text-slate-400">Responsável</span>
                    <h2 class="text-lg sm:text-xl font-bold text-slate-900">{{ $interessado->pessoa?->nome ?? 'Família' }}</h2>
                    @if($interessado->pessoa?->telefone)
                        <p class="text-xs text-slate-500 mt-0.5">📞 {{ $interessado->pessoa->telefone }}</p>
                    @endif
                </div>

                @if($interessado->dependentes->isNotEmpty())
                    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100 sm:text-right">
                        <span class="text-[11px] font-semibold uppercase text-slate-400 block mb-0.5">Aluno(s) Pretendente(s)</span>
                        <div class="space-y-0.5">
                            @foreach($interessado->dependentes as $dep)
                                <p class="text-xs font-bold text-slate-800">
                                    {{ $dep->nome_crianca }}
                                    <span class="text-indigo-600 font-normal">({{ $dep->serie?->nome ?? 'Série a definir' }})</span>
                                </p>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Progresso Geral dos Documentos -->
            <div class="pt-4 space-y-2">
                <div class="flex justify-between items-center text-xs font-semibold">
                    <span class="text-slate-600">Progresso dos Documentos:</span>
                    <span class="{{ $todosDocsContratoEntregues ? 'text-emerald-600' : 'text-indigo-600' }}">
                        {{ $progresso['aprovados'] + $progresso['em_analise'] }} de {{ $progresso['total'] }} enviados
                        @if($todosDocsContratoEntregues)
                            <span class="ml-1 text-emerald-700 font-bold">(Documentos OK ✅)</span>
                        @else
                            <span class="ml-1 text-amber-700">(Documentos pendentes ⏳)</span>
                        @endif
                    </span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                    <div class="h-2.5 rounded-full transition-all duration-500 {{ $todosDocsContratoEntregues ? 'bg-emerald-500' : 'bg-indigo-600' }}" style="width: {{ $progresso['percentual'] }}%"></div>
                </div>
                <p class="text-[11px] text-slate-400">
                    Você pode fotografar os documentos pelo celular ou anexar arquivos em PDF.
                </p>
            </div>
        </div>

        @php
            $statusAbas = $statusAbas ?? $interessado->resumoPendenciasPortal();
        @endphp

        <!-- Abas de Navegação Unificada -->
        <div class="flex flex-wrap sm:flex-nowrap border-b border-slate-200 gap-2 sm:gap-4 text-xs sm:text-sm font-semibold">
            <!-- Aba 1: 1. Cadastro -->
            <a href="{{ route('candidato.documentos.show', ['token' => $token, 'aba' => 'dados']) }}"
               class="pb-3 px-2 border-b-2 transition-colors flex items-center gap-1.5 {{ $abaAtiva === 'dados' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <span>1. Cadastro</span>
                @if($statusAbas['dados']['tem_pendencia'])
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200" title="{{ implode(', ', $statusAbas['dados']['pendencias']) }}">
                        {{ $statusAbas['dados']['quantidade'] }}
                    </span>
                @else
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200" title="Cadastro concluído">
                        ✓
                    </span>
                @endif
            </a>

            <!-- Aba 2: 2. Documentos -->
            <a href="{{ route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos']) }}"
               class="pb-3 px-2 border-b-2 transition-colors flex items-center gap-1.5 {{ $abaAtiva === 'documentos' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <span>2. Documentos</span>
                @if($statusAbas['documentos']['tem_pendencia'])
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200" title="{{ $statusAbas['documentos']['quantidade'] }} documento(s) obrigatório(s) pendente(s)">
                        {{ $statusAbas['documentos']['quantidade'] }}
                    </span>
                @else
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200" title="Documentos obrigatórios concluídos">
                        ✓
                    </span>
                @endif
            </a>
        </div>

        <!-- CONTEÚDO DA ABA 1: DADOS CADASTRAIS -->
        @if($abaAtiva === 'dados')
            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 shadow-xs space-y-6">
                <div class="border-b border-slate-100 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                            <span>📝</span> Cadastro dos Dados da Família e dos Alunos
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Estes dados alimentam diretamente a ficha cadastral e o contrato de prestação de serviços educacionais.
                        </p>
                    </div>
                    @if(! $statusAbas['dados']['tem_pendencia'])
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 self-start sm:self-auto">
                            <span>✓</span> Cadastro Concluído
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 self-start sm:self-auto">
                            <span>⏳</span> {{ $statusAbas['dados']['quantidade'] }} {{ $statusAbas['dados']['quantidade'] === 1 ? 'pendência' : 'pendências' }}
                        </span>
                    @endif
                </div>

                @if($etapaFamiliaConcluida)
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span class="text-base">🔒</span>
                            <span><strong>Modo Somente Leitura:</strong> Seus dados já foram enviados e estão sob análise da secretaria. Edições estão desativadas.</span>
                        </div>
                        <a href="{{ route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos']) }}" class="text-indigo-600 hover:text-indigo-800 font-semibold shrink-0 inline-flex items-center gap-1">
                            <span>Ver Documentos Enviados</span>
                            <span>→</span>
                        </a>
                    </div>
                @endif

                <form method="POST" action="{{ route('candidato.documentos.dados', ['token' => $token]) }}" class="space-y-6">
                    @csrf

                    @php
                        $respOld = old('responsavel', $dadosPreMatricula['responsaveis'][0] ?? []);
                        $segundoOld = old('segundo_responsavel', $dadosPreMatricula['responsaveis'][1] ?? []);
                    @endphp

                    <!-- Responsável Principal -->
                    <fieldset class="border border-slate-200 rounded-xl p-4 space-y-4" {{ $etapaFamiliaConcluida ? 'disabled' : '' }}>
                        <legend class="text-xs font-bold uppercase tracking-wider text-indigo-700 px-2">Responsável Principal (Financeiro)</legend>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div class="sm:col-span-2">
                                <label class="block font-semibold text-slate-700 mb-1">Nome Completo *</label>
                                <input type="text" name="responsavel[nome]" value="{{ $respOld['nome'] ?? $interessado->pessoa?->nome }}" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">CPF *</label>
                                <input type="text" name="responsavel[cpf]" id="input-resp-cpf" data-mask="cpf" inputmode="numeric" value="{{ $respOld['cpf'] ?? $interessado->pessoa?->cpf }}" required placeholder="000.000.000-00" maxlength="14" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>

                            @php
                                $dataNascRespRaw = $respOld['data_nascimento'] ?? $interessado->pessoa?->data_nascimento;
                                $dataNascResp = '';
                                if (filled($dataNascRespRaw)) {
                                    if ($dataNascRespRaw instanceof \DateTimeInterface) {
                                        $dataNascResp = $dataNascRespRaw->format('d/m/Y');
                                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $dataNascRespRaw)) {
                                        $dataNascResp = \Carbon\Carbon::parse($dataNascRespRaw)->format('d/m/Y');
                                    } else {
                                        $dataNascResp = (string) $dataNascRespRaw;
                                    }
                                }
                            @endphp
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Data de Nascimento *</label>
                                <input type="text" name="responsavel[data_nascimento]" id="input-resp-data-nascimento" data-mask="data" inputmode="numeric" placeholder="DD/MM/AAAA" maxlength="10" value="{{ $dataNascResp }}" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Telefone / WhatsApp *</label>
                                <input type="text" name="responsavel[telefone]" id="input-resp-telefone" data-mask="telefone" inputmode="tel" value="{{ $respOld['telefone'] ?? $interessado->pessoa?->telefone }}" required placeholder="(00) 00000-0000" maxlength="15" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">E-mail</label>
                                <input type="email" name="responsavel[email]" value="{{ $respOld['email'] ?? $interessado->pessoa?->email }}" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Grau de Parentesco / Vínculo *</label>
                                <select name="responsavel[tipo_vinculo_id]" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                                    @foreach($tiposVinculo as $vinculoId => $vinculoNome)
                                        <option value="{{ $vinculoId }}" {{ (string)($respOld['tipo_vinculo_id'] ?? '') === (string)$vinculoId ? 'selected' : '' }}>
                                             {{ $vinculoNome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex items-center gap-2 pt-5">
                                <input type="hidden" name="responsavel[is_financeiro]" value="1">
                                <span class="text-xs font-semibold text-emerald-700">✓ Responsável Financeiro pelo Contrato</span>
                            </div>
                        </div>

                        <!-- Endereço Residencial -->
                        <div class="pt-3 border-t border-slate-100">
                            <span class="text-xs font-bold text-slate-700 block mb-3">Endereço Residencial</span>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">CEP *</label>
                                    <div class="relative">
                                        <input type="text" name="responsavel[cep]" id="input-cep" data-mask="cep" inputmode="numeric" value="{{ $respOld['cep'] ?? '' }}" required placeholder="00000-000" maxlength="9" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 pr-8">
                                        <span id="cep-loading" class="hidden absolute right-2.5 top-2.5 text-xs text-indigo-600 animate-spin font-bold">⏳</span>
                                    </div>
                                    <span id="cep-feedback" class="text-[11px] text-slate-500 block mt-1"></span>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block font-semibold text-slate-600 mb-1">Logradouro / Rua *</label>
                                    <input type="text" name="responsavel[logradouro]" id="input-logradouro" value="{{ $respOld['logradouro'] ?? '' }}" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Número *</label>
                                    <input type="text" name="responsavel[numero]" id="input-numero" value="{{ $respOld['numero'] ?? '' }}" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Bairro *</label>
                                    <input type="text" name="responsavel[bairro]" id="input-bairro" value="{{ $respOld['bairro'] ?? '' }}" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Cidade *</label>
                                    <input type="text" name="responsavel[cidade]" id="input-cidade" value="{{ $respOld['cidade'] ?? $respOld['cidade_nome'] ?? '' }}" required class="w-full px-3 py-2 border rounded-lg text-sm bg-slate-50 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Estado (UF) *</label>
                                    <input type="text" name="responsavel[uf]" id="input-uf" maxlength="2" value="{{ $respOld['uf'] ?? '' }}" required class="w-full px-3 py-2 border rounded-lg text-sm bg-slate-50 uppercase focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block font-semibold text-slate-600 mb-1">Complemento</label>
                                    <input type="text" name="responsavel[complemento]" id="input-complemento" value="{{ $respOld['complemento'] ?? '' }}" placeholder="Apto, Bloco..." class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <input type="hidden" name="responsavel[cidade_ibge]" id="input-cidade-ibge" value="{{ $respOld['cidade_ibge'] ?? '' }}">
                            </div>
                        </div>
                    </fieldset>

                    <!-- Segundo Responsável (Opcional) -->
                    <details class="border border-slate-200 rounded-xl p-4 text-xs" {{ filled($segundoOld['nome'] ?? null) ? 'open' : '' }}>
                        <summary class="font-bold text-slate-700 cursor-pointer hover:text-indigo-600">
                            + Adicionar Segundo Responsável (opcional)
                        </summary>
                        <fieldset class="border-0 p-0 m-0" {{ $etapaFamiliaConcluida ? 'disabled' : '' }}>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 pt-3 border-t border-slate-100">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Nome Completo</label>
                                    <input type="text" name="segundo_responsavel[nome]" value="{{ $segundoOld['nome'] ?? '' }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">CPF</label>
                                    <input type="text" name="segundo_responsavel[cpf]" data-mask="cpf" inputmode="numeric" placeholder="000.000.000-00" maxlength="14" value="{{ $segundoOld['cpf'] ?? '' }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Telefone</label>
                                    <input type="text" name="segundo_responsavel[telefone]" data-mask="telefone" inputmode="tel" placeholder="(00) 00000-0000" maxlength="15" value="{{ $segundoOld['telefone'] ?? '' }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Grau de Vínculo</label>
                                    <select name="segundo_responsavel[tipo_vinculo_id]" class="w-full px-3 py-2 border rounded-lg text-sm">
                                        <option value="">Selecione...</option>
                                        @foreach($tiposVinculo as $vinculoId => $vinculoNome)
                                            <option value="{{ $vinculoId }}" {{ (string)($segundoOld['tipo_vinculo_id'] ?? '') === (string)$vinculoId ? 'selected' : '' }}>
                                                {{ $vinculoNome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </fieldset>
                    </details>

                    <!-- Aluno(s) / Dependente(s) -->
                    <fieldset class="border border-slate-200 rounded-xl p-4 space-y-4" {{ $etapaFamiliaConcluida ? 'disabled' : '' }}>
                        <legend class="text-xs font-bold uppercase tracking-wider text-indigo-700 px-2">Aluno(s) Pretendente(s)</legend>

                        @foreach($interessado->dependentes as $index => $dependente)
                            @php
                                $depOld = old("dependentes.{$index}", $dadosPreMatricula['alunos'][$dependente->id] ?? []);
                            @endphp
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-3 text-xs">
                                <input type="hidden" name="dependentes[{{ $index }}][id]" value="{{ $dependente->id }}">

                                <div class="font-bold text-slate-800 text-sm flex items-center justify-between">
                                    <span>Estudante: {{ $dependente->nome_crianca }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800">Aluno #{{ $index + 1 }}</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Série / Ano Pretendido *</label>
                                        <select name="dependentes[{{ $index }}][serie_id]" required class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                                            @foreach($series as $s)
                                                <option value="{{ $s->id }}" {{ (string)($depOld['serie_id'] ?? $dependente->serie_id) === (string)$s->id ? 'selected' : '' }}>
                                                    {{ $s->curso?->nome_interno }} - {{ $s->nome }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Turno de Preferência</label>
                                        <select name="dependentes[{{ $index }}][turno_preferencia]" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                                            @foreach(['Manhã', 'Tarde', 'Integral', 'Sem preferência'] as $turno)
                                                <option value="{{ $turno }}" {{ ($depOld['turno_preferencia'] ?? '') === $turno ? 'selected' : '' }}>
                                                    {{ $turno }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    @php
                                        $dataNascDepRaw = $depOld['data_nascimento'] ?? $dependente->data_nascimento;
                                        $dataNascDep = '';
                                        if (filled($dataNascDepRaw)) {
                                            if ($dataNascDepRaw instanceof \DateTimeInterface) {
                                                $dataNascDep = $dataNascDepRaw->format('d/m/Y');
                                            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $dataNascDepRaw)) {
                                                $dataNascDep = \Carbon\Carbon::parse($dataNascDepRaw)->format('d/m/Y');
                                            } else {
                                                $dataNascDep = (string) $dataNascDepRaw;
                                            }
                                        }
                                    @endphp
                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Data de Nascimento *</label>
                                        <input type="text" name="dependentes[{{ $index }}][data_nascimento]" data-mask="data" inputmode="numeric" placeholder="DD/MM/AAAA" maxlength="10" value="{{ $dataNascDep }}" required class="w-full px-3 py-2 border rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500">
                                    </div>

                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">CPF do Aluno (se possuir)</label>
                                        <input type="text" name="dependentes[{{ $index }}][cpf]" data-mask="cpf" inputmode="numeric" value="{{ $depOld['cpf'] ?? '' }}" placeholder="000.000.000-00" maxlength="14" class="w-full px-3 py-2 border rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500">
                                    </div>

                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Sexo</label>
                                        <select name="dependentes[{{ $index }}][sexo]" class="w-full px-3 py-2 border rounded-lg text-sm bg-white">
                                            <option value="">Selecione...</option>
                                            @foreach($sexos as $sexo)
                                                <option value="{{ $sexo->value }}" {{ ($depOld['sexo'] ?? '') === $sexo->value ? 'selected' : '' }}>
                                                    {{ $sexo->getLabel() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </fieldset>

                    <!-- Aceite LGPD -->
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-2">
                        <label class="flex items-start gap-2.5 font-medium cursor-pointer">
                            <input type="checkbox" name="lgpd_aceite" value="1" required {{ filled($dadosPreMatricula) ? 'checked' : '' }} {{ $etapaFamiliaConcluida ? 'disabled' : '' }} class="mt-0.5 rounded text-indigo-600 focus:ring-indigo-500">
                            <span>
                                Declaro que as informações acima são verdadeiras e autorizo o tratamento dos dados pessoais fornecidos para fins de cadastro, formalização de proposta pré-contratual e procedimentos de matrícula escolar, nos termos da Lei Geral de Proteção de Dados (Lei nº 13.709/2018).
                            </span>
                        </label>
                    </div>

                    @if($etapaFamiliaConcluida)
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-center space-y-2">
                            <p class="text-xs text-slate-600 font-medium">
                                Dados cadastrais enviados e em análise pela secretaria escolar. Não é necessária nenhuma ação adicional aqui.
                            </p>
                            <a href="{{ route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos']) }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-xs text-xs transition-colors">
                                <span>Consultar Documentos Enviados</span>
                                <span>→</span>
                            </a>
                        </div>
                    @else
                        <button type="submit" class="w-full py-3.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-xs text-sm transition-colors flex items-center justify-center gap-2">
                            <span>Salvar Dados e Avançar para Documentos</span>
                            <span>→</span>
                        </button>
                    @endif
                </form>
            </div>
        @endif

        <!-- CONTEÚDO DA ABA 2: DOCUMENTOS -->
        @if($abaAtiva === 'documentos')
            <div class="space-y-6">
                @if(! $statusAbas['documentos']['tem_pendencia'])
                    <div class="p-5 sm:p-6 rounded-2xl bg-gradient-to-br from-emerald-50 via-teal-50/40 to-emerald-50 border border-emerald-200 shadow-xs space-y-4">
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-xl shrink-0 shadow-xs font-bold">
                                ✓
                            </div>
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-slate-900 text-base sm:text-lg leading-snug">
                                        Documentação Recebida com Sucesso! 🎉
                                    </h3>
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        {{ $statusAbas['documentos']['enviados'] }}/{{ $statusAbas['documentos']['total_obrigatorios'] }} obrigatórios enviados
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                    Recebemos todos os seus dados cadastrais e documentos obrigatórios para a pré-matrícula. Nossa <strong>Secretaria Escolar</strong> já iniciou a conferência das informações.
                                </p>
                            </div>
                        </div>

                        <div class="bg-white/90 rounded-xl border border-emerald-100 p-4 space-y-2 text-xs">
                            <span class="font-bold text-slate-800 flex items-center gap-1.5 text-xs">
                                <span>📋</span> Próximos Passos:
                            </span>
                            <ol class="list-decimal list-inside space-y-1.5 text-slate-600 leading-relaxed">
                                <li>Nossa equipe realizará a conferência detalhada dos documentos enviados.</li>
                                <li>Em breve entraremos em contato para apresentar as opções de anuidade escolar e parcelamento.</li>
                                <li>Você receberá as orientações finais para a formalização do contrato e matrícula.</li>
                            </ol>
                        </div>

                        <div class="flex items-center gap-2 pt-1 text-xs border-t border-emerald-100/80 text-slate-500">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Status atual: <strong class="text-emerald-700">Aguardando Análise da Secretaria</strong></span>
                        </div>
                    </div>
                @else
                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 flex items-center justify-between gap-3 text-xs shadow-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-base">⏳</span>
                            <span>Restam <strong>{{ $statusAbas['documentos']['quantidade'] }} documento(s) obrigatório(s)</strong> a serem anexados para a pré-matrícula.</span>
                        </div>
                        <span class="font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-900">
                            {{ $statusAbas['documentos']['enviados'] }}/{{ $statusAbas['documentos']['total_obrigatorios'] }} enviados
                        </span>
                    </div>
                @endif

                <!-- Seção 1: Documentos Obrigatórios (Em Destaque) -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                                <span class="text-rose-600">🔴</span> Documentos Obrigatórios
                            </h3>
                            <p class="text-xs text-slate-500">
                                <strong>Indispensáveis:</strong> Documentos essenciais para a conferência e efetivação da pré-matrícula.
                            </p>
                        </div>
                    </div>

                    @if($docsContrato->isEmpty())
                        <div class="p-4 bg-white rounded-xl border border-slate-200 text-xs text-slate-500">
                            Nenhum documento obrigatório configurado para esta série.
                        </div>
                    @else
                        @foreach($docsContrato as $tipo)
                            @include('candidato.partials.item-documento', ['tipo' => $tipo, 'categoria' => 'contrato', 'etapaFamiliaConcluida' => $etapaFamiliaConcluida])
                        @endforeach
                    @endif
                </div>

                <!-- Seção 2: Documentos para o Histórico Escolar (Colapsado) -->
                <details class="group bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs transition-all">
                    <summary class="p-4 sm:p-5 flex items-center justify-between cursor-pointer select-none hover:bg-slate-50 transition-colors list-none">
                        <div class="flex items-center gap-2.5">
                            <span class="text-amber-500 text-lg">🟡</span>
                            <div>
                                <h3 class="font-bold text-slate-900 text-sm sm:text-base flex items-center gap-2 flex-wrap">
                                    <span>Documentos para o Histórico Escolar</span>
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Documentação acadêmica</span>
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Exigidos para a pasta pedagógica do aluno (envio facultado neste momento inicial).
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-slate-400 group-open:rotate-180 transition-transform duration-200">
                            <span class="text-xs font-semibold hidden sm:inline text-slate-500">{{ $docsHistorico->count() }} {{ $docsHistorico->count() === 1 ? 'documento' : 'documentos' }}</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </summary>

                    <div class="p-4 sm:p-5 border-t border-slate-100 space-y-3 bg-slate-50/50">
                        @if($docsHistorico->isEmpty())
                            <div class="p-4 bg-white rounded-xl border border-slate-200 text-xs text-slate-500">
                                Nenhum documento de histórico específico requerido para esta série no momento.
                            </div>
                        @else
                            @foreach($docsHistorico as $tipo)
                                @include('candidato.partials.item-documento', ['tipo' => $tipo, 'categoria' => 'historico', 'etapaFamiliaConcluida' => $etapaFamiliaConcluida])
                            @endforeach
                        @endif
                    </div>
                </details>

                <!-- Seção 3: Documentos Opcionais / Complementares (Colapsado) -->
                <details class="group bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs transition-all">
                    <summary class="p-4 sm:p-5 flex items-center justify-between cursor-pointer select-none hover:bg-slate-50 transition-colors list-none">
                        <div class="flex items-center gap-2.5">
                            <span class="text-emerald-500 text-lg">🟢</span>
                            <div>
                                <h3 class="font-bold text-slate-900 text-sm sm:text-base flex items-center gap-2 flex-wrap">
                                    <span>Documentos Opcionais / Complementares</span>
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Envio facultativo</span>
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Envio caso o aluno possua (ex: carteirinha de convênio, laudos, declarações).
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-slate-400 group-open:rotate-180 transition-transform duration-200">
                            <span class="text-xs font-semibold hidden sm:inline text-slate-500">{{ $docsOpcionais->count() }} {{ $docsOpcionais->count() === 1 ? 'documento' : 'documentos' }}</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </summary>

                    <div class="p-4 sm:p-5 border-t border-slate-100 space-y-3 bg-slate-50/50">
                        @if($docsOpcionais->isEmpty())
                            <div class="p-4 bg-white rounded-xl border border-slate-200 text-xs text-slate-500">
                                Nenhum documento opcional listado para esta série.
                            </div>
                        @else
                            @foreach($docsOpcionais as $tipo)
                                @include('candidato.partials.item-documento', ['tipo' => $tipo, 'categoria' => 'opcional', 'etapaFamiliaConcluida' => $etapaFamiliaConcluida])
                            @endforeach
                        @endif
                    </div>
                </details>
            </div>
        @endif
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function formatCpf(v) {
                v = (v || '').replace(/\D/g, '').slice(0, 11);
                if (v.length > 9) return v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
                if (v.length > 6) return v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
                if (v.length > 3) return v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
                return v;
            }

            function formatPhone(v) {
                v = (v || '').replace(/\D/g, '').slice(0, 11);
                if (v.length > 10) return v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                if (v.length > 6) return v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                if (v.length > 2) return v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
                return v;
            }

            function formatDate(v) {
                v = (v || '').replace(/\D/g, '').slice(0, 8);
                if (v.length > 4) return v.replace(/(\d{2})(\d{2})(\d{1,4})/, '$1/$2/$3');
                if (v.length > 2) return v.replace(/(\d{2})(\d{1,2})/, '$1/$2');
                return v;
            }

            function formatCep(v) {
                v = (v || '').replace(/\D/g, '').slice(0, 8);
                if (v.length > 5) return v.replace(/(\d{5})(\d{1,3})/, '$1-$2');
                return v;
            }

            function applyMask(el) {
                const mask = el.getAttribute('data-mask');
                if (!mask) return;

                if (mask === 'cpf') el.value = formatCpf(el.value);
                else if (mask === 'telefone') el.value = formatPhone(el.value);
                else if (mask === 'data') el.value = formatDate(el.value);
                else if (mask === 'cep') el.value = formatCep(el.value);
            }

            // Aplica máscaras nos inputs existentes
            document.querySelectorAll('[data-mask]').forEach(function (el) {
                applyMask(el);
                el.addEventListener('input', function () { applyMask(el); });
                el.addEventListener('blur', function () { applyMask(el); });
            });

            // Autocomplete de CEP via ViaCEP
            const cepInput = document.getElementById('input-cep');
            if (cepInput) {
                let ultimoCepConsultado = '';

                async function handleConsultaCep() {
                    const raw = (cepInput.value || '').replace(/\D/g, '');
                    if (raw.length !== 8 || raw === ultimoCepConsultado) {
                        return;
                    }
                    ultimoCepConsultado = raw;

                    const loading = document.getElementById('cep-loading');
                    const feedback = document.getElementById('cep-feedback');
                    const inputLogradouro = document.getElementById('input-logradouro');
                    const inputBairro = document.getElementById('input-bairro');
                    const inputCidade = document.getElementById('input-cidade');
                    const inputUf = document.getElementById('input-uf');
                    const inputIbge = document.getElementById('input-cidade-ibge');
                    const inputNumero = document.getElementById('input-numero');

                    if (loading) loading.classList.remove('hidden');
                    if (feedback) {
                        feedback.innerText = 'Buscando CEP...';
                        feedback.className = 'text-[11px] text-indigo-600 font-medium block mt-1';
                    }

                    try {
                        const response = await fetch(`https://viacep.com.br/ws/${raw}/json/`);
                        const data = await response.json();

                        if (data.erro) {
                            if (feedback) {
                                feedback.innerText = 'CEP não encontrado. Por favor, digite os dados manualmente.';
                                feedback.className = 'text-[11px] text-amber-600 block mt-1';
                            }
                            return;
                        }

                        if (inputLogradouro && data.logradouro) inputLogradouro.value = data.logradouro;
                        if (inputBairro && data.bairro) inputBairro.value = data.bairro;
                        if (inputCidade && data.localidade) inputCidade.value = data.localidade;
                        if (inputUf && data.uf) inputUf.value = data.uf;
                        if (inputIbge && data.ibge) inputIbge.value = data.ibge;

                        if (feedback) {
                            feedback.innerText = `✓ ${data.localidade} - ${data.uf} (${data.bairro || 'Endereço localizado'})`;
                            feedback.className = 'text-[11px] text-emerald-600 font-semibold block mt-1';
                        }

                        if (inputNumero) {
                            inputNumero.focus();
                        }
                    } catch (error) {
                        if (feedback) {
                            feedback.innerText = 'Não foi possível buscar o endereço automaticamente. Preencha manualmente.';
                            feedback.className = 'text-[11px] text-slate-500 block mt-1';
                        }
                    } finally {
                        if (loading) loading.classList.add('hidden');
                    }
                }

                cepInput.addEventListener('input', function () {
                    const raw = this.value.replace(/\D/g, '');
                    if (raw.length === 8) {
                        handleConsultaCep();
                    }
                });

                cepInput.addEventListener('blur', handleConsultaCep);
            }
        });
    </script>
</body>
</html>
