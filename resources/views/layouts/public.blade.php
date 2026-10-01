<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Matrícula Online 100% Digital e Self-Service — Torre360 Educação">
    <title>{{ $title ?? 'Matrícula Online 100% Digital' }} — Torre360</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full flex flex-col font-sans text-slate-800 antialiased selection:bg-primary-500 selection:text-white">

    {{-- Topbar de Identificação --}}
    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-primary-900 to-primary-700 flex items-center justify-center text-white font-black text-lg shadow-sm">
                    T
                </div>
                <div>
                    <span class="text-base font-extrabold tracking-tight text-slate-900">Torre360</span>
                    <span class="block text-[10px] uppercase font-semibold tracking-wider text-primary-600">Educação e Gestão</span>
                </div>
            </a>

            <div class="flex items-center gap-3">
                <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Matrículas Abertas 2026
                </span>

                <a href="{{ route('filament.portal.auth.login') }}"
                   class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-primary-700 transition">
                    Já sou responsável &rarr;
                </a>
            </div>
        </div>
    </header>

    {{-- Conteúdo Principal --}}
    <main class="flex-1">
        @if (isset($slot))
            {{ $slot }}
        @else
            @yield('content')
        @endif
    </main>

    {{-- Rodapé Institucional --}}
    <footer class="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4">
            <p>&copy; {{ date('Y') }} Torre360 Gestão Escolar. Todos os direitos reservados &bull; Ambiente seguro com validação digital.</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
