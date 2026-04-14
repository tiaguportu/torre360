<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Torre360 — Sistema de Gestão Escolar de Elite</title>
    <meta name="description" content="Gestão escolar moderna, eficiente e profissional. Torre360: A solução completa para instituições que buscam a excelência.">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS V4 CDN -->
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>

    <style>
        :root {
            --color-primary: #312783;
            --color-accent: #ddaf00;
            --color-bg: #f8fafc;
            --color-text: #1e293b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--color-bg);
            color: var(--color-text);
            scroll-behavior: smooth;
        }

        h1, h2, h3 {
            font-family: 'Outfit', sans-serif;
            color: var(--color-primary);
        }

        .glass-light {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(49, 39, 131, 0.1);
            border-radius: 32px;
        }

        .btn-navy {
            background-color: var(--color-primary);
            color: white;
            transition: all 0.3s ease;
        }

        .btn-navy:hover {
            background-color: #251b66;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(49, 39, 131, 0.2);
        }

        .btn-gold {
            background-color: var(--color-accent);
            color: white;
            transition: all 0.3s ease;
        }

        .btn-gold:hover {
            background-color: #c49b00;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(221, 175, 0, 0.2);
        }

        .nav-link {
            transition: color 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            color: var(--color-accent);
        }

        .card-feature {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            transition: all 0.4s ease;
            border: 1px solid transparent;
        }

        .card-feature:hover {
            border-color: var(--color-accent);
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        .img-mask {
            border-radius: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
        }

        section {
            padding: 100px 20px;
        }

        input, textarea {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            width: 100%;
            transition: border-color 0.3s ease;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            background: white;
        }
    </style>
</head>
<body class="antialiased">

    <!-- Navbar -->
    <nav class="fixed top-0 w-full z-50 px-8 py-5 flex justify-between items-center transition-all duration-300 bg-white/80 backdrop-blur-lg border-b border-slate-200">
        <a href="/" class="flex items-center gap-3 decoration-0">
            <img src="/logo-adaptative.svg" alt="Torre360" class="h-10 w-auto">
            <span class="text-2xl font-bold tracking-tight text-[#312783]">Torre360</span>
        </a>
        <div class="hidden md:flex gap-10">
            <a href="#solucao" class="nav-link text-slate-600">Solução</a>
            <a href="#mobile" class="nav-link text-slate-600">Mobile</a>
            <a href="#contato" class="nav-link text-slate-600">Contato</a>
        </div>
        <div>
            <a href="/admin" class="btn-navy px-6 py-2.5 rounded-full text-sm font-semibold">
                Painel Administrativo
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="min-h-screen flex items-center pt-20">
        <div class="container mx-auto grid md:grid-cols-2 gap-16 items-center">
            <div class="space-y-10">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-100 border border-slate-200 text-slate-500 text-sm font-medium">
                    <span class="w-2 h-2 rounded-full bg-gold animate-pulse"></span>
                    Gestão Escolar Elevada à Excelência
                </div>
                <h1 class="text-5xl md:text-7xl font-extrabold leading-[1.1] text-[#312783]">
                    Sua escola sob uma <br><span class="text-[#ddaf00]">Nova Perspectiva.</span>
                </h1>
                <p class="text-xl text-slate-500 max-w-xl leading-relaxed">
                    Mais que um software, uma inteligência centralizada para transformar a rotina acadêmica, otimizar o financeiro e converter novos alunos com facilidade.
                </p>
                <div class="flex flex-wrap gap-5">
                    <a href="#contato" class="btn-gold px-10 py-4 rounded-2xl font-bold text-lg">
                        Solicitar Acesso Grátis
                    </a>
                    <a href="#solucao" class="px-10 py-4 rounded-2xl border border-slate-300 font-bold text-lg text-slate-600 hover:bg-slate-50 transition-colors">
                        Ver Recursos
                    </a>
                </div>
            </div>
            <div class="relative">
                <img src="/images/landing/hero.png" alt="Torre360 Workflow" class="img-mask w-full float shadow-none ring-1 ring-slate-200">
                <div class="absolute -bottom-6 -left-6 glass-light p-6 shadow-xl hidden md:block">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center font-bold">✓</div>
                        <div>
                            <p class="text-sm font-bold text-slate-800">Boletins Automatizados</p>
                            <p class="text-xs text-slate-500">Pronto para o Período Letivo</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Solucao Section -->
    <section id="solucao" class="bg-slate-50 border-y border-slate-200">
        <div class="container mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-20 space-y-4">
                <h2 class="text-4xl md:text-5xl font-bold">Tudo em um só lugar.</h2>
                <p class="text-lg text-slate-500">Elimine a fragmentação. Integramos todos os pilares da sua instituição em um ecossistema fluido e amigável.</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-10">
                <div class="card-feature">
                    <div class="w-16 h-16 bg-[#312783]/10 rounded-2xl flex items-center justify-center mb-8">
                        <svg class="w-8 h-8 text-[#312783]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-5">Gestão Acadêmica</h3>
                    <ul class="space-y-4 text-slate-500 font-medium">
                        <li>• Boletins inteligentes por etapa</li>
                        <li>• Lançamento rápido de notas</li>
                        <li>• Cronogramas e Frequência</li>
                        <li>• Edição facilitada de grades</li>
                    </ul>
                </div>

                <div class="card-feature">
                    <div class="w-16 h-16 bg-[#ddaf00]/10 rounded-2xl flex items-center justify-center mb-8">
                        <svg class="w-8 h-8 text-[#ddaf00]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-5">Financeiro Blindado</h3>
                    <ul class="space-y-4 text-slate-500 font-medium">
                        <li>• Conciliação bancária automática</li>
                        <li>• Geração de faturas e itens</li>
                        <li>• Relatórios DRE automáticos</li>
                        <li>• Gestão de planos de contas</li>
                    </ul>
                </div>

                <div class="card-feature">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mb-8">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-5">CRM & Prospecção</h3>
                    <ul class="space-y-4 text-slate-500 font-medium">
                        <li>• Funil de leads customizável</li>
                        <li>• Histórico completo de contatos</li>
                        <li>• Captação integrada ao site</li>
                        <li>• Gestão de documentos pendentes</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Mobile Section -->
    <section id="mobile">
        <div class="container mx-auto grid md:grid-cols-2 gap-20 items-center">
            <div class="order-2 md:order-1">
                <img src="/images/landing/mobile.png" alt="Torre360 App Mobile" class="img-mask w-full max-w-md mx-auto">
            </div>
            <div class="order-1 md:order-2 space-y-10">
                <h2 class="text-4xl md:text-5xl font-bold leading-tight">O controle na palma da sua mão.</h2>
                <p class="text-lg text-slate-500">O sistema mobile do Torre360 não é redimensionado, ele é pensado para dispositivos móveis. Rapidez e praticidade para quem decide.</p>
                <div class="grid grid-cols-2 gap-8">
                    <div class="space-y-3">
                        <p class="text-3xl font-bold text-[#ddaf00]">100%</p>
                        <p class="font-semibold text-slate-700">Adaptativo</p>
                        <p class="text-sm text-slate-500 small">Tabelas e gráficos que se transformam no seu celular.</p>
                    </div>
                    <div class="space-y-3">
                        <p class="text-3xl font-bold text-[#312783]">Real-time</p>
                        <p class="font-semibold text-slate-700">Push Notifications</p>
                        <p class="text-sm text-slate-500 small">Fique por dentro de cada movimento financeiro ou acadêmico.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contato Section -->
    <section id="contato" class="bg-[#312783] text-white">
        <div class="container mx-auto">
            <div class="grid md:grid-cols-2 gap-20">
                <div class="space-y-10">
                    <h2 class="text-4xl md:text-5xl font-bold text-white">Preparado para revolucionar sua gestão?</h2>
                    <p class="text-xl text-blue-100/70">Preencha os dados e nosso time de especialistas entrará em contato para agendar uma demonstração exclusiva para sua escola.</p>
                    
                    <div class="space-y-6">
                        <div class="flex items-center gap-5">
                            <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center">📞</div>
                            <p class="text-lg font-semibold">(11) 99999-9999</p>
                        </div>
                        <div class="flex items-center gap-5">
                            <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center">✉️</div>
                            <p class="text-lg font-semibold">contato@escolatorredemarfim.com.br</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-10 rounded-[32px] text-slate-800 shadow-2xl">
                    @if(session('success'))
                        <div class="bg-green-100 border border-green-200 text-green-700 p-6 rounded-2xl mb-6 flex items-center gap-4">
                            <span class="text-2xl">✅</span>
                            <p class="font-bold">{{ session('success') }}</p>
                        </div>
                    @endif

                    <form action="{{ route('solicitar-acesso') }}" method="POST" class="space-y-6">
                        @csrf
                        <div class="grid grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label class="block text-sm font-bold text-slate-700 mb-2">Nome Completo</label>
                                <input type="text" name="nome" placeholder="Como podemos te chamar?" required>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-bold text-slate-700 mb-2">E-mail Corporativo</label>
                                <input type="email" name="email" placeholder="seuemail@escola.com.br" required>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-bold text-slate-700 mb-2">WhatsApp / Telefone</label>
                                <input type="text" name="whatsapp" placeholder="(00) 00000-0000">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-bold text-slate-700 mb-2">Mensagem (Opcional)</label>
                                <textarea name="mensagem" rows="3" placeholder="Conte um pouco sobre sua necessidade..."></textarea>
                            </div>
                        </div>
                        <button type="submit" class="w-full btn-gold py-5 rounded-2xl font-bold text-lg uppercase tracking-wide">
                            Solicitar Demonstração Grátis
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 bg-slate-100 border-t border-slate-200 px-8">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="flex items-center gap-3">
                <img src="/logo-adaptative.svg" alt="Torre360 Logo" class="h-8 w-auto">
                <span class="text-xl font-bold text-[#312783]">Torre360</span>
            </div>
            <p class="text-slate-500 text-sm">© 2026 Torre360 Gestão Escolar. Todos os direitos reservados.</p>
            <div class="flex gap-6">
                <!-- Adicionar links se houver -->
            </div>
        </div>
    </footer>

</body>
</html>
