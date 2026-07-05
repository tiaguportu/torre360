<!DOCTYPE html>
@php
    $emailsSignatarios = $contrato->getSignatarios()->pluck('email');
@endphp
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Contrato #{{ $contrato->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
    @filamentStyles
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .max-w-4xl {
                max-width: 100% !important;
                width: 100% !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .overflow-y-auto {
                overflow: visible !important;
                padding: 0 !important;
            }
            .border-b {
                border-bottom: none !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen py-10 px-4">
    <div class="max-w-4xl mx-auto bg-white shadow-xl rounded-lg overflow-hidden flex flex-col">
        
        <!-- Header fixo / Barra de Ações -->
        <div class="bg-gray-800 text-white p-4 flex justify-between items-center no-print">
            <h1 class="text-xl font-bold">Visualização do Contrato</h1>
            <div class="flex gap-4">
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded font-medium transition">
                    Imprimir / Salvar PDF
                </button>
                <a href="/admin/contratos" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded font-medium transition">
                    Voltar
                </a>
            </div>
        </div>

        <!-- Conteúdo do Contrato (Blade Reutilizada) -->
        <div class="p-8 sm:p-12 overflow-y-auto bg-white text-gray-900 border-b">
            <!-- Mensagens de Erro/Sucesso da Sessão (Fallback) -->
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6 no-print" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6 no-print" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @include('pdfs.contrato', [
                'contrato' => $contrato,
                'matricula' => $matricula,
                'aluno' => $aluno,
                'responsavel' => $responsavel,
                'serie' => $serie,
                'curso' => $curso,
                'periodoLetivo' => $periodoLetivo,
                'conteudo_template' => $conteudo_template ?? null,
            ])
        </div>

        <!-- Footer com ação de Assinatura -->
        <div class="bg-gray-50 p-8 text-center no-print">
            @if($contrato->assinafy_id && in_array($contrato->assinafy_status, ['signed', 'completed']))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Este contrato já foi assinado digitalmente!</strong>
                </div>
                <a href="{{ route('contratos.download', $contrato) }}" class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transform transition hover:scale-105">
                    Baixar Contrato Assinado
                </a>
            @else
                <p class="text-gray-600 mb-6 max-w-lg mx-auto">
                    Ao clicar no botão abaixo, você será redirecionado para a plataforma <strong>Assinafy</strong> para realizar a assinatura digital deste documento.
                </p>
                <form action="{{ route('contratos.gerar-assinatura', $contrato) }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-4 px-12 rounded-full shadow-lg transform transition hover:scale-105 text-lg">
                        Ciente e Aceito: Iniciar Assinatura Digital
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Overlay de Redirecionamento/Preparação -->
    <div id="loading-overlay" class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-50 flex items-center justify-center hidden no-print">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full mx-4 text-center border border-gray-100 transform transition-all">
            <!-- Spinner Moderno -->
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-orange-100 text-orange-600 mb-6 animate-pulse">
                <svg class="w-8 h-8 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            
            <h3 class="text-xl font-bold text-gray-900 mb-2">Preparando Documento</h3>
            <p class="text-gray-600 mb-6 text-sm">
                Você será redirecionado para a plataforma de assinaturas <strong>Assinafy</strong> em instantes. Por favor, não feche esta janela.
            </p>
            
            <!-- Alerta de E-mail Enviado -->
            <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 text-left text-sm text-orange-850">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-orange-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <div>
                        <span class="font-semibold block mb-1">E-mail de assinatura enviado para:</span>
                        <ul class="list-disc list-inside space-y-0.5 font-medium text-orange-950">
                            @foreach($emailsSignatarios as $email)
                                <li>{{ $email }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('form')?.addEventListener('submit', function() {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) {
                overlay.classList.remove('hidden');
            }
            const btn = this.querySelector('button');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = 'Processando...';
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });
    </script>

    @livewire('notifications')

    @livewireScripts
    @filamentScripts
</body>
</html>
