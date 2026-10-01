@extends('layouts.public', ['title' => 'Matrícula Realizada com Sucesso!'])

@section('content')
<div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto space-y-8">
        
        {{-- Hero de Confirmação --}}
        <div class="rounded-3xl bg-gradient-to-br from-emerald-600 via-teal-700 to-emerald-900 p-8 sm:p-12 text-white shadow-xl relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-64 h-64 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
            <div class="relative z-10 flex flex-col sm:flex-row items-center sm:items-start gap-6 text-center sm:text-left">
                <div class="h-20 w-20 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center shrink-0 border border-white/30 text-white shadow-inner">
                    <svg class="w-10 h-10 text-emerald-200" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <div class="space-y-2 flex-1">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 text-xs font-semibold uppercase tracking-wider text-emerald-100 border border-white/20">
                        <span class="w-2 h-2 rounded-full bg-emerald-300 animate-ping"></span>
                        Requerimento 100% Digital Concluído
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                        Parabéns! Matrícula Efetuada com Sucesso
                    </h1>
                    <p class="text-emerald-100 text-sm sm:text-base leading-relaxed max-w-2xl">
                        A solicitação de matrícula para <span class="font-bold text-white">{{ $aluno->nome }}</span> foi registrada em nosso sistema institucional com aceite digital e protocolo formal.
                    </p>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-white/20 flex flex-wrap items-center justify-between gap-4 text-xs sm:text-sm text-emerald-100">
                <div>
                    <span class="block text-emerald-200/80 font-medium">Protocolo da Matrícula:</span>
                    <span class="text-lg font-mono font-bold text-white">#{{ str_pad($matricula->id, 6, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div>
                    <span class="block text-emerald-200/80 font-medium">Data do Registro:</span>
                    <span class="font-semibold text-white">{{ now()->format('d/m/Y \à\s H:i:s') }}</span>
                </div>
                <div>
                    <span class="block text-emerald-200/80 font-medium">Situação Atual:</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-400 text-amber-950">
                        Pendente de Homologação
                    </span>
                </div>
            </div>
        </div>

        {{-- Card de Resumo Completo --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-8 print:shadow-none print:border-none">
            
            {{-- Dados da Matrícula e Turma --}}
            <div>
                <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                    </svg>
                    Resumo Acadêmico
                </h2>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-xs font-medium text-slate-500 block">Aluno(a)</span>
                        <span class="font-bold text-slate-900 block mt-0.5">{{ $aluno->nome }}</span>
                        @if($aluno->cpf)
                            <span class="text-xs text-slate-500 font-mono">CPF: ***.{{ substr($aluno->cpf, 3, 3) }}.***-**</span>
                        @endif
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-xs font-medium text-slate-500 block">Curso & Nível</span>
                        <span class="font-bold text-slate-900 block mt-0.5">{{ $turma->serie?->curso?->nome ?? 'Curso Base' }}</span>
                        <span class="text-xs text-slate-500">{{ $turma->serie?->nome ?? 'Série' }}</span>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-xs font-medium text-slate-500 block">Turma & Turno</span>
                        <span class="font-bold text-slate-900 block mt-0.5">{{ $turma->nome }}</span>
                        <span class="text-xs text-slate-500 capitalize">{{ $turma->turno ?? 'Matutino' }}</span>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-xs font-medium text-slate-500 block">Ano / Período Letivo</span>
                        <span class="font-bold text-slate-900 block mt-0.5">{{ $turma->periodoLetivo?->nome ?? date('Y') }}</span>
                        <span class="text-xs text-emerald-600 font-semibold">Vaga Assegurada</span>
                    </div>
                </div>
            </div>

            {{-- Dados do Responsável Legal & Financeiro --}}
            @if($responsavel)
            <div>
                <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    Responsável Cadastrado
                </h2>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-xs font-medium text-slate-500 block">Nome Completo</span>
                        <span class="font-bold text-slate-900 block mt-0.5">{{ $responsavel->nome }}</span>
                        <span class="text-xs text-slate-500">Responsável Financeiro & Legal</span>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-xs font-medium text-slate-500 block">E-mail de Contato</span>
                        <span class="font-bold text-slate-900 block mt-0.5">{{ $responsavel->email ?? 'Não informado' }}</span>
                        <span class="text-xs text-slate-500">Recebeu confirmação de acesso</span>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-xs font-medium text-slate-500 block">Telefone / WhatsApp</span>
                        <span class="font-bold text-slate-900 block mt-0.5">{{ $responsavel->telefone ?? 'Não informado' }}</span>
                        <span class="text-xs text-slate-500">Contato principal</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- Contrato e Assinatura Digital --}}
            @if($contrato)
            <div>
                <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    Aceite Eletrônico & Garantia Jurídica (LGPD)
                </h2>
                <div class="mt-4 p-4 rounded-2xl bg-emerald-50/60 border border-emerald-200/60 text-xs text-emerald-950 space-y-2">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <span class="font-semibold text-emerald-900">Termo de Prestação de Serviços Educacionais Assinado Digitalmente</span>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-200 text-emerald-800 font-bold text-[11px]">Válido e Autenticado</span>
                    </div>
                    <p class="font-mono text-[11px] text-emerald-800 break-all bg-white/70 p-2.5 rounded-xl border border-emerald-200">
                        {{ $contrato->log_assinatura }}
                    </p>
                </div>
            </div>
            @endif

            {{-- Documentos Enviados --}}
            @if($documentos->isNotEmpty())
            <div>
                <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    Documentos Digitais Anexados ({{ $documentos->count() }})
                </h2>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($documentos as $doc)
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
                            <div class="flex items-center gap-2.5 truncate">
                                <span class="p-1.5 rounded-lg bg-primary-100 text-primary-700">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </span>
                                <div class="truncate">
                                    <span class="font-semibold text-slate-800 block truncate">{{ $doc->tipoDocumento?->nome ?? 'Documento' }}</span>
                                    <span class="text-slate-400 text-[10px] block truncate">{{ $doc->nome_arquivo_original }}</span>
                                </div>
                            </div>
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800">
                                Em Análise
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Card de Instruções e Acesso ao Portal --}}
            <div class="p-6 rounded-3xl bg-gradient-to-br from-primary-900 to-primary-800 text-white shadow-md relative overflow-hidden print:hidden">
                <div class="relative z-10 flex flex-col sm:flex-row items-center justify-between gap-6">
                    <div class="space-y-1.5 text-center sm:text-left">
                        <span class="text-xs font-semibold uppercase tracking-wider text-primary-200">Acesso Familiar Disponível</span>
                        <h3 class="text-xl font-bold">Acompanhe pelo Portal da Família</h3>
                        <p class="text-xs text-primary-100 max-w-lg leading-relaxed">
                            Enviamos um e-mail com as instruções de primeiro acesso. No portal você acompanha a validação dos documentos, quadro de horários, financeiro e informativos da instituição.
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                        <a href="{{ route('filament.portal.auth.login') }}"
                           class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-white text-primary-900 font-bold text-sm hover:bg-primary-50 transition shadow-sm">
                            Acessar Portal da Família &rarr;
                        </a>
                    </div>
                </div>
            </div>

            {{-- Botões de Ação na Base --}}
            <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4 print:hidden">
                <a href="{{ route('home') }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-900 transition">
                    &larr; Voltar à Página Inicial
                </a>

                <div class="flex items-center gap-3">
                    <button type="button"
                            onclick="window.print()"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50 transition shadow-sm">
                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Imprimir Comprovante
                    </button>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection
