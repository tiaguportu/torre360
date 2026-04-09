<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Contrato #{{ $contrato->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
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
            @include('pdfs.contrato', [
                'contrato' => $contrato,
                'matricula' => $matricula,
                'aluno' => $aluno,
                'responsavel' => $responsavel,
                'serie' => $serie,
                'curso' => $curso,
                'periodoLetivo' => $periodoLetivo
            ])
        </div>

        <!-- Footer com ação de Assinatura -->
        <div class="bg-gray-50 p-8 text-center no-print">
            @if($contrato->assinafy_id && in_array($contrato->assinafy_status, ['signed', 'completed']))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Este contrato já foi assinado digitalmente!</strong>
                </div>
                <a href="{{ route('contratos.download', $contrato) }}" target="_blank" class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-full shadow-lg transform transition hover:scale-105">
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

    <!-- Script para feedback de carregamento -->
    <script>
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = this.querySelector('button');
            btn.disabled = true;
            btn.innerHTML = 'Processando...';
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        });
    </script>
</body>
</html>
