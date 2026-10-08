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
    <script>
        // Paleta da marca (mesmo azul-marinho do painel: #243468) no lugar do índigo padrão do Tailwind.
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#f2f5fb',
                            100: '#e4e9f5',
                            200: '#c9d3ea',
                            300: '#a0b0d8',
                            400: '#6f86bf',
                            500: '#3a4f8f',
                            600: '#243468',
                            700: '#1d2a55',
                            800: '#172144',
                            900: '#111832',
                        },
                    },
                },
            },
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }

        /* Campos de formulário: borda mais firme e foco na cor da marca */
        main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]),
        main select { border-color: #cbd5e1; }
        main input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]):focus,
        main select:focus { outline: none; border-color: #243468; }
        fieldset:disabled input, fieldset:disabled select { background-color: #f8fafc; color: #64748b; }

        a:focus-visible, button:focus-visible, summary:focus-visible {
            outline: 2px solid #3a4f8f; outline-offset: 2px; border-radius: 6px;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">
    <!-- Topbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="h-[3px] bg-brand-600"></div>
        <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <img src="{{ asset('logo-adaptative.svg') }}" alt="Torre360" class="w-10 h-10 object-contain shrink-0">
                <div class="min-w-0">
                    <h1 class="font-semibold text-slate-900 text-sm sm:text-base leading-tight truncate">Portal de Pré-Admissão</h1>
                    <p class="text-xs text-slate-500 truncate">Matrícula & Checklist Digital de Documentos</p>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 w-full max-w-3xl mx-auto px-4 pt-8 pb-10 space-y-6">
        <!-- Feedback Messages -->
        @if(session('sucesso'))
            <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-start gap-3" role="status">
                <x-heroicon-o-check-circle class="h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm font-medium">{{ session('sucesso') }}</div>
            </div>
        @endif

        @if(session('erro'))
            <div class="p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 flex items-start gap-3" role="alert">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0 text-rose-600" />
                <div class="text-sm font-medium">{{ session('erro') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-sm space-y-1" role="alert">
                <span class="font-semibold block mb-1">Por favor, verifique os campos abaixo:</span>
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
            <section class="bg-white rounded-xl border border-slate-200 border-l-4 border-l-emerald-600 shadow-sm p-5 sm:p-6">
                <div class="flex items-start gap-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">
                        <x-heroicon-o-check class="h-5 w-5" />
                    </span>
                    <div class="space-y-2 min-w-0">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700">
                                Etapa da Família Concluída
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20">
                                <x-heroicon-s-clock class="h-3.5 w-3.5" /> Aguardando Validação da Secretaria
                            </span>
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold tracking-tight text-slate-900 leading-snug">
                            Tudo pronto por aqui! Sua pré-matrícula foi enviada com sucesso.
                        </h2>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Você concluiu o cadastro e o envio de todos os documentos obrigatórios. As abas abaixo permanecem disponíveis para você consultar o que foi enviado (em <strong class="font-semibold text-slate-800">modo de somente leitura</strong>).
                        </p>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-500">
                    <span class="flex items-start gap-2">
                        <x-heroicon-o-lock-closed class="h-4 w-4 shrink-0 text-slate-400" />
                        <span><strong class="font-semibold text-slate-700">Modo Somente Leitura:</strong> Formulário e uploads bloqueados para a conferência oficial da secretaria.</span>
                    </span>
                </div>
            </section>
        @endif

        <!-- Card de Identificação e Resumo do Candidato -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm">
            <div class="p-5 sm:p-6 grid gap-5 sm:grid-cols-2">
                <div class="min-w-0">
                    <span class="text-[11px] font-semibold tracking-wider uppercase text-slate-500">Responsável</span>
                    <h2 class="mt-1 text-lg font-semibold tracking-tight text-slate-900">{{ $interessado->pessoa?->nome ?? 'Família' }}</h2>
                    @if($interessado->pessoa?->telefone)
                        <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
                            <x-heroicon-o-phone class="h-4 w-4 text-slate-400" /> {{ $interessado->pessoa->telefone }}
                        </p>
                    @endif
                </div>

                @if($interessado->dependentes->isNotEmpty())
                    <div class="sm:border-l sm:border-slate-100 sm:pl-6">
                        <span class="text-[11px] font-semibold tracking-wider uppercase text-slate-500">Aluno(s) Pretendente(s)</span>
                        <ul class="mt-1 space-y-1">
                            @foreach($interessado->dependentes as $dep)
                                <li class="text-sm">
                                    <span class="font-semibold text-slate-900">{{ $dep->nome_crianca }}</span>
                                    <span class="text-slate-500">· {{ $dep->serie?->nome ?? 'Série a definir' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <!-- Progresso Geral dos Documentos -->
            <div class="px-5 sm:px-6 py-4 border-t border-slate-100 bg-slate-50/70 rounded-b-xl">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
                    <span class="font-medium text-slate-700">Progresso dos Documentos</span>
                    <span class="flex items-center gap-2.5 tabular-nums">
                        <span class="text-slate-600">{{ $progresso['aprovados'] + $progresso['em_analise'] }} de {{ $progresso['total'] }} enviados</span>
                        @if($todosDocsContratoEntregues)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700">
                                <x-heroicon-s-check-circle class="h-4 w-4" /> Documentos OK
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700">
                                <x-heroicon-s-clock class="h-4 w-4" /> Documentos pendentes
                            </span>
                        @endif
                    </span>
                </div>
                <div class="mt-2.5 w-full bg-slate-200 rounded-full h-1.5 overflow-hidden" role="progressbar" aria-label="Progresso dos documentos" aria-valuenow="{{ $progresso['percentual'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-1.5 rounded-full transition-all duration-500 {{ $todosDocsContratoEntregues ? 'bg-emerald-600' : 'bg-brand-600' }}" style="width: {{ $progresso['percentual'] }}%"></div>
                </div>
                <p class="mt-2.5 flex items-center gap-1.5 text-xs text-slate-500">
                    <x-heroicon-o-information-circle class="h-4 w-4 shrink-0 text-slate-400" />
                    Você pode fotografar os documentos pelo celular ou anexar arquivos em PDF.
                </p>
            </div>
        </section>

        @php
            $statusAbas = $statusAbas ?? $interessado->resumoPendenciasPortal();
        @endphp

        <!-- Abas de Navegação Unificada -->
        <nav class="flex gap-6 border-b border-slate-200 text-sm font-medium" aria-label="Etapas da pré-admissão">
            <!-- Aba 1: 1. Cadastro -->
            <a href="{{ route('candidato.documentos.show', ['token' => $token, 'aba' => 'dados']) }}"
               @if($abaAtiva === 'dados') aria-current="page" @endif
               class="-mb-px pb-3 border-b-2 transition-colors flex items-center gap-2 {{ $abaAtiva === 'dados' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                <span>1. Cadastro</span>
                @if($statusAbas['dados']['tem_pendencia'])
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-semibold rounded-full bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200" title="{{ implode(', ', $statusAbas['dados']['pendencias']) }}">
                        {{ $statusAbas['dados']['quantidade'] }}
                    </span>
                @else
                    <span class="inline-flex items-center justify-center h-5 w-5 rounded-full bg-emerald-100 text-emerald-700" title="Cadastro concluído">
                        <x-heroicon-s-check class="h-3 w-3" />
                    </span>
                @endif
            </a>

            <!-- Aba 2: 2. Documentos -->
            <a href="{{ route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos']) }}"
               @if($abaAtiva === 'documentos') aria-current="page" @endif
               class="-mb-px pb-3 border-b-2 transition-colors flex items-center gap-2 {{ $abaAtiva === 'documentos' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                <span>2. Documentos</span>
                @if($statusAbas['documentos']['tem_pendencia'])
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-semibold rounded-full bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-200" title="{{ $statusAbas['documentos']['quantidade'] }} documento(s) obrigatório(s) pendente(s)">
                        {{ $statusAbas['documentos']['quantidade'] }}
                    </span>
                @else
                    <span class="inline-flex items-center justify-center h-5 w-5 rounded-full bg-emerald-100 text-emerald-700" title="Documentos obrigatórios concluídos">
                        <x-heroicon-s-check class="h-3 w-3" />
                    </span>
                @endif
            </a>
        </nav>

        <!-- CONTEÚDO DA ABA 1: DADOS CADASTRAIS -->
        @if($abaAtiva === 'dados')
            <div class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 shadow-sm space-y-6">
                <div class="border-b border-slate-100 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold tracking-tight text-slate-900 text-base">
                            Cadastro dos Dados da Família e dos Alunos
                        </h3>
                        <p class="text-sm text-slate-500 mt-1">
                            Estes dados alimentam diretamente a ficha cadastral e o contrato de prestação de serviços educacionais.
                        </p>
                    </div>
                    @if(! $statusAbas['dados']['tem_pendencia'])
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 self-start sm:self-auto shrink-0">
                            <x-heroicon-s-check-circle class="h-4 w-4" /> Cadastro Concluído
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-md bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20 self-start sm:self-auto shrink-0">
                            <x-heroicon-s-clock class="h-4 w-4" /> {{ $statusAbas['dados']['quantidade'] }} {{ $statusAbas['dados']['quantidade'] === 1 ? 'pendência' : 'pendências' }}
                        </span>
                    @endif
                </div>

                @if($etapaFamiliaConcluida)
                    <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 text-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-start gap-2.5">
                            <x-heroicon-o-lock-closed class="h-5 w-5 shrink-0 text-slate-400" />
                            <span><strong class="font-semibold">Modo Somente Leitura:</strong> Seus dados já foram enviados e estão sob análise da secretaria. Edições estão desativadas.</span>
                        </div>
                        <a href="{{ route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos']) }}" class="text-brand-700 hover:text-brand-900 font-semibold shrink-0 inline-flex items-center gap-1.5">
                            <span>Ver Documentos Enviados</span>
                            <x-heroicon-o-arrow-right class="h-4 w-4" />
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
                        <legend class="text-xs font-bold uppercase tracking-wider text-brand-700 px-2">Responsável Principal (Financeiro)</legend>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div class="sm:col-span-2">
                                <label class="block font-semibold text-slate-700 mb-1">Nome Completo *</label>
                                <input type="text" name="responsavel[nome]" value="{{ $respOld['nome'] ?? $interessado->pessoa?->nome }}" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-brand-500 text-sm">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">CPF *</label>
                                <input type="text" name="responsavel[cpf]" id="input-resp-cpf" data-mask="cpf" inputmode="numeric" value="{{ $respOld['cpf'] ?? $interessado->pessoa?->cpf }}" required placeholder="000.000.000-00" maxlength="14" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-brand-500 text-sm">
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
                                <input type="text" name="responsavel[data_nascimento]" id="input-resp-data-nascimento" data-mask="data" inputmode="numeric" placeholder="DD/MM/AAAA" maxlength="10" value="{{ $dataNascResp }}" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-brand-500 text-sm">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Telefone / WhatsApp *</label>
                                <input type="text" name="responsavel[telefone]" id="input-resp-telefone" data-mask="telefone" inputmode="tel" value="{{ $respOld['telefone'] ?? $interessado->pessoa?->telefone }}" required placeholder="(00) 00000-0000" maxlength="15" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-brand-500 text-sm">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">E-mail</label>
                                <input type="email" name="responsavel[email]" value="{{ $respOld['email'] ?? $interessado->pessoa?->email }}" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-brand-500 text-sm">
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Grau de Parentesco / Vínculo *</label>
                                <select name="responsavel[tipo_vinculo_id]" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-brand-500 text-sm">
                                    @foreach($tiposVinculo as $vinculoId => $vinculoNome)
                                        <option value="{{ $vinculoId }}" {{ (string)($respOld['tipo_vinculo_id'] ?? '') === (string)$vinculoId ? 'selected' : '' }}>
                                             {{ $vinculoNome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex items-center gap-2 pt-5">
                                <input type="hidden" name="responsavel[is_financeiro]" value="1">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                                    <x-heroicon-s-check-circle class="h-4 w-4" /> Responsável Financeiro pelo Contrato
                                </span>
                            </div>
                        </div>

                        <!-- Endereço Residencial -->
                        <div class="pt-3 border-t border-slate-100">
                            <span class="text-xs font-bold text-slate-700 block mb-3">Endereço Residencial</span>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">CEP *</label>
                                    <div class="relative">
                                        <input type="text" name="responsavel[cep]" id="input-cep" data-mask="cep" inputmode="numeric" value="{{ $respOld['cep'] ?? '' }}" required placeholder="00000-000" maxlength="9" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-brand-500 pr-8">
                                        <span id="cep-loading" class="hidden absolute right-2.5 top-2.5 text-brand-600 animate-spin" aria-hidden="true">
                                            <x-heroicon-o-arrow-path class="h-4 w-4" />
                                        </span>
                                    </div>
                                    <span id="cep-feedback" class="text-[11px] text-slate-500 block mt-1"></span>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block font-semibold text-slate-600 mb-1">Logradouro / Rua *</label>
                                    <input type="text" name="responsavel[logradouro]" id="input-logradouro" value="{{ $respOld['logradouro'] ?? '' }}" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-brand-500">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Número *</label>
                                    <input type="text" name="responsavel[numero]" id="input-numero" value="{{ $respOld['numero'] ?? '' }}" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-brand-500">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Bairro *</label>
                                    <input type="text" name="responsavel[bairro]" id="input-bairro" value="{{ $respOld['bairro'] ?? '' }}" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-brand-500">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Cidade *</label>
                                    <input type="text" name="responsavel[cidade]" id="input-cidade" value="{{ $respOld['cidade'] ?? $respOld['cidade_nome'] ?? '' }}" required class="w-full px-3 py-2 border rounded-lg text-sm bg-slate-50 focus:ring-2 focus:ring-brand-500">
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Estado (UF) *</label>
                                    <input type="text" name="responsavel[uf]" id="input-uf" maxlength="2" value="{{ $respOld['uf'] ?? '' }}" required class="w-full px-3 py-2 border rounded-lg text-sm bg-slate-50 uppercase focus:ring-2 focus:ring-brand-500">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block font-semibold text-slate-600 mb-1">Complemento</label>
                                    <input type="text" name="responsavel[complemento]" id="input-complemento" value="{{ $respOld['complemento'] ?? '' }}" placeholder="Apto, Bloco..." class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-brand-500">
                                </div>
                                <input type="hidden" name="responsavel[cidade_ibge]" id="input-cidade-ibge" value="{{ $respOld['cidade_ibge'] ?? '' }}">
                            </div>
                        </div>
                    </fieldset>

                    <!-- Segundo Responsável (Opcional) -->
                    <details class="border border-slate-200 rounded-xl p-4 text-xs" {{ filled($segundoOld['nome'] ?? null) ? 'open' : '' }}>
                        <summary class="font-bold text-slate-700 cursor-pointer hover:text-brand-600">
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
                        <legend class="text-xs font-bold uppercase tracking-wider text-brand-700 px-2">Aluno(s) Pretendente(s)</legend>

                        @foreach($interessado->dependentes as $index => $dependente)
                            @php
                                $depOld = old("dependentes.{$index}", $dadosPreMatricula['alunos'][$dependente->id] ?? []);
                            @endphp
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-3 text-xs">
                                <input type="hidden" name="dependentes[{{ $index }}][id]" value="{{ $dependente->id }}">

                                <div class="font-bold text-slate-800 text-sm flex items-center justify-between">
                                    <span>Estudante: {{ $dependente->nome_crianca }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-brand-100 text-brand-800">Aluno #{{ $index + 1 }}</span>
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
                                        <input type="text" name="dependentes[{{ $index }}][data_nascimento]" data-mask="data" inputmode="numeric" placeholder="DD/MM/AAAA" maxlength="10" value="{{ $dataNascDep }}" required class="w-full px-3 py-2 border rounded-lg text-sm bg-white focus:ring-2 focus:ring-brand-500">
                                    </div>

                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">CPF do Aluno (se possuir)</label>
                                        <input type="text" name="dependentes[{{ $index }}][cpf]" data-mask="cpf" inputmode="numeric" value="{{ $depOld['cpf'] ?? '' }}" placeholder="000.000.000-00" maxlength="14" class="w-full px-3 py-2 border rounded-lg text-sm bg-white focus:ring-2 focus:ring-brand-500">
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
                    <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-2">
                        <label class="flex items-start gap-2.5 font-medium cursor-pointer">
                            <input type="checkbox" name="lgpd_aceite" value="1" required {{ filled($dadosPreMatricula) ? 'checked' : '' }} {{ $etapaFamiliaConcluida ? 'disabled' : '' }} class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span>
                                Declaro que as informações acima são verdadeiras e autorizo o tratamento dos dados pessoais fornecidos para fins de cadastro, formalização de proposta pré-contratual e procedimentos de matrícula escolar, nos termos da Lei Geral de Proteção de Dados (Lei nº 13.709/2018).
                            </span>
                        </label>
                    </div>

                    @if($etapaFamiliaConcluida)
                        <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 text-center space-y-3">
                            <p class="text-sm text-slate-600">
                                Dados cadastrais enviados e em análise pela secretaria escolar. Não é necessária nenhuma ação adicional aqui.
                            </p>
                            <a href="{{ route('candidato.documentos.show', ['token' => $token, 'aba' => 'documentos']) }}" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg shadow-sm text-sm transition-colors">
                                <span>Consultar Documentos Enviados</span>
                                <x-heroicon-o-arrow-right class="h-4 w-4" />
                            </a>
                        </div>
                    @else
                        <button type="submit" class="w-full py-3 px-4 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg shadow-sm text-sm transition-colors flex items-center justify-center gap-2">
                            <span>Salvar Dados e Avançar para Documentos</span>
                            <x-heroicon-o-arrow-right class="h-4 w-4" />
                        </button>
                    @endif
                </form>
            </div>
        @endif

        <!-- CONTEÚDO DA ABA 2: DOCUMENTOS -->
        @if($abaAtiva === 'documentos')
            <div class="space-y-6">
                @if(! $statusAbas['documentos']['tem_pendencia'])
                    <section class="bg-white rounded-xl border border-slate-200 border-l-4 border-l-emerald-600 shadow-sm p-5 sm:p-6 space-y-5">
                        <div class="flex items-start gap-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                <x-heroicon-o-check class="h-5 w-5" />
                            </span>
                            <div class="space-y-1.5 min-w-0">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                    <h3 class="font-semibold tracking-tight text-slate-900 text-base sm:text-lg leading-snug">
                                        Documentação Recebida com Sucesso!
                                    </h3>
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 tabular-nums">
                                        {{ $statusAbas['documentos']['enviados'] }}/{{ $statusAbas['documentos']['total_obrigatorios'] }} obrigatórios enviados
                                    </span>
                                </div>
                                <p class="text-sm text-slate-600 leading-relaxed">
                                    Recebemos todos os seus dados cadastrais e documentos obrigatórios para a pré-matrícula. Nossa <strong class="font-semibold text-slate-800">Secretaria Escolar</strong> já iniciou a conferência das informações.
                                </p>
                            </div>
                        </div>

                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 space-y-3">
                            <span class="font-semibold text-slate-800 text-sm">Próximos Passos:</span>
                            <ol class="space-y-2.5 text-sm text-slate-600 leading-relaxed">
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[11px] font-semibold text-brand-700 ring-1 ring-inset ring-brand-200">1</span>
                                    <span>Nossa equipe realizará a conferência detalhada dos documentos enviados.</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[11px] font-semibold text-brand-700 ring-1 ring-inset ring-brand-200">2</span>
                                    <span>Em breve entraremos em contato para apresentar as opções de anuidade escolar e parcelamento.</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[11px] font-semibold text-brand-700 ring-1 ring-inset ring-brand-200">3</span>
                                    <span>Você receberá as orientações finais para a formalização do contrato e matrícula.</span>
                                </li>
                            </ol>
                        </div>

                        <div class="flex items-center gap-2 pt-4 text-sm text-slate-500 border-t border-slate-100">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            <span>Status atual: <strong class="font-semibold text-slate-800">Aguardando Análise da Secretaria</strong></span>
                        </div>
                    </section>
                @else
                    <div class="px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 flex items-start sm:items-center justify-between gap-3 text-sm">
                        <div class="flex items-start sm:items-center gap-2.5">
                            <x-heroicon-o-exclamation-circle class="h-5 w-5 shrink-0 text-amber-600" />
                            <span>Restam <strong class="font-semibold">{{ $statusAbas['documentos']['quantidade'] }} documento(s) obrigatório(s)</strong> a serem anexados para a pré-matrícula.</span>
                        </div>
                        <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-md bg-white text-amber-900 ring-1 ring-inset ring-amber-300 tabular-nums">
                            {{ $statusAbas['documentos']['enviados'] }}/{{ $statusAbas['documentos']['total_obrigatorios'] }} enviados
                        </span>
                    </div>
                @endif

                <!-- Seção 1: Documentos Obrigatórios (Em Destaque) -->
                <section class="space-y-3">
                    <div>
                        <h3 class="font-semibold tracking-tight text-slate-900 text-base">Documentos Obrigatórios</h3>
                        <p class="mt-0.5 text-sm text-slate-500">
                            <strong class="font-medium text-slate-700">Indispensáveis:</strong> Documentos essenciais para a conferência e efetivação da pré-matrícula.
                        </p>
                    </div>

                    @if($docsContrato->isEmpty())
                        <div class="p-4 bg-white rounded-xl border border-slate-200 text-sm text-slate-500">
                            Nenhum documento obrigatório configurado para esta série.
                        </div>
                    @else
                        @foreach($docsContrato as $tipo)
                            @include('candidato.partials.item-documento', ['tipo' => $tipo, 'categoria' => 'contrato', 'etapaFamiliaConcluida' => $etapaFamiliaConcluida])
                        @endforeach
                    @endif
                </section>

                <!-- Seção 2: Documentos para o Histórico Escolar (Colapsado) -->
                <details class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <summary class="p-4 sm:p-5 flex items-center justify-between gap-3 cursor-pointer select-none hover:bg-slate-50 transition-colors list-none [&::-webkit-details-marker]:hidden">
                        <div class="min-w-0">
                            <h3 class="font-semibold tracking-tight text-slate-900 text-sm sm:text-base flex items-center gap-2 flex-wrap">
                                <span>Documentos para o Histórico Escolar</span>
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">Documentação acadêmica</span>
                            </h3>
                            <p class="mt-0.5 text-sm text-slate-500">
                                Exigidos para a pasta pedagógica do aluno (envio facultado neste momento inicial).
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 text-slate-400">
                            <span class="text-xs font-medium hidden sm:inline text-slate-500 tabular-nums">{{ $docsHistorico->count() }} {{ $docsHistorico->count() === 1 ? 'documento' : 'documentos' }}</span>
                            <x-heroicon-o-chevron-down class="h-5 w-5 transition-transform duration-200 group-open:rotate-180" />
                        </div>
                    </summary>

                    <div class="p-4 sm:p-5 border-t border-slate-100 space-y-3 bg-slate-50/60">
                        @if($docsHistorico->isEmpty())
                            <div class="p-4 bg-white rounded-xl border border-slate-200 text-sm text-slate-500">
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
                <details class="group bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <summary class="p-4 sm:p-5 flex items-center justify-between gap-3 cursor-pointer select-none hover:bg-slate-50 transition-colors list-none [&::-webkit-details-marker]:hidden">
                        <div class="min-w-0">
                            <h3 class="font-semibold tracking-tight text-slate-900 text-sm sm:text-base flex items-center gap-2 flex-wrap">
                                <span>Documentos Opcionais / Complementares</span>
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">Envio facultativo</span>
                            </h3>
                            <p class="mt-0.5 text-sm text-slate-500">
                                Envio caso o aluno possua (ex: carteirinha de convênio, laudos, declarações).
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 text-slate-400">
                            <span class="text-xs font-medium hidden sm:inline text-slate-500 tabular-nums">{{ $docsOpcionais->count() }} {{ $docsOpcionais->count() === 1 ? 'documento' : 'documentos' }}</span>
                            <x-heroicon-o-chevron-down class="h-5 w-5 transition-transform duration-200 group-open:rotate-180" />
                        </div>
                    </summary>

                    <div class="p-4 sm:p-5 border-t border-slate-100 space-y-3 bg-slate-50/60">
                        @if($docsOpcionais->isEmpty())
                            <div class="p-4 bg-white rounded-xl border border-slate-200 text-sm text-slate-500">
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

    <footer class="py-6 text-center text-xs text-slate-400">
        <div class="max-w-3xl mx-auto px-4">
            &copy; {{ date('Y') }} Torre360 Gestão Escolar
        </div>
    </footer>

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
                        feedback.className = 'text-[11px] text-brand-600 font-medium block mt-1';
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
