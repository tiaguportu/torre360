<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Torre360 — Inteligência em Gestão Escolar</title>
    <meta name="description" content="A evolução da gestão escolar. Centralize acadêmico, financeiro e CRM em uma única plataforma premium.">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS V4 CDN -->
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>

    <style>
        :root {
            --color-primary: #0F172A;
            --color-accent: #FDE68A;
            --color-secondary: #3B82F6;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--color-primary);
            color: white;
            scroll-behavior: smooth;
            overflow-x: hidden;
        }

        h1, h2, h3 {
            font-family: 'Outfit', sans-serif;
        }

        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
        }

        .gradient-text {
            background: linear-gradient(135deg, #FFF 0%, var(--color-accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-glow {
            position: absolute;
            top: -10%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
            z-index: -1;
        }

        .float {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }

        .btn-premium {
            background: linear-gradient(135deg, #FDE68A 0%, #F59E0B 100%);
            color: #0F172A;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(252, 211, 77, 0.3);
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(252, 211, 77, 0.5);
        }

        .card-hover:hover {
            border-color: rgba(252, 211, 77, 0.4);
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-5px);
            transition: all 0.4s ease;
        }
    </style>
</head>
<body class="antialiased">

    <!-- Hero Glow Background -->
    <div class="hero-glow"></div>

    <!-- Navigation -->
    <nav class="fixed top-0 w-full z-50 px-6 py-4 flex justify-between items-center bg-primary/80 backdrop-blur-md border-b border-white/10">
        <div class="flex items-center gap-2">
            <span class="text-2xl font-bold tracking-tighter gradient-text">Torre360</span>
        </div>
        <div class="hidden md:flex gap-8 text-sm font-medium text-white/70">
            <a href="#modulos" class="hover:text-accent transition-colors">Módulos</a>
            <a href="#mobile" class="hover:text-accent transition-colors">Mobile</a>
            <a href="#seguranca" class="hover:text-accent transition-colors">Segurança</a>
        </div>
        <div>
            <a href="/admin" class="px-5 py-2 rounded-full border border-accent/50 text-accent text-sm font-semibold hover:bg-accent hover:text-primary transition-all">
                Área Administrativa
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative min-h-screen pt-32 pb-20 px-6 flex items-center">
        <div class="container mx-auto grid md:grid-cols-2 gap-12 items-center">
            <div class="space-y-8">
                <div class="inline-block px-4 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-bold uppercase tracking-widest">
                    A Evolução da Gestão Escolar
                </div>
                <h1 class="text-5xl md:text-7xl font-bold leading-tight">
                    A inteligência que sua <span class="gradient-text">escola precisa</span>.
                </h1>
                <p class="text-lg text-white/60 max-w-lg leading-relaxed">
                    Centralize o acadêmico, controle o financeiro e potencialize seu CRM. Uma visão 360 do seu negócio educacional em uma plataforma premium e intuitiva.
                </p>
                <div class="flex flex-wrap gap-4 pt-4">
                    <a href="#contato" class="btn-premium px-8 py-4 rounded-xl font-bold text-lg">
                        Solicitar Demonstração
                    </a>
                    <a href="#modulos" class="px-8 py-4 rounded-xl border border-white/20 font-bold text-lg hover:bg-white/5 transition-colors">
                        Conhecer Módulos
                    </a>
                </div>
            </div>
            <div class="relative flex justify-center">
                <div class="absolute inset-0 bg-blue-500/20 blur-[120px] rounded-full"></div>
                <img src="/images/landing/hero.png" alt="Torre360 Dashboard" class="relative z-10 w-full max-w-2xl float rounded-2xl shadow-2xl">
            </div>
        </div>
    </section>

    <!-- Modulos Section -->
    <section id="modulos" class="py-24 px-6 bg-white/[0.02]">
        <div class="container mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">Gestão 360 do seu negócio</h2>
                <p class="text-white/60">Tudo o que você precisa para gerenciar uma instituição de ensino moderna, do primeiro contato com o aluno à análise financeira de resultados.</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Academico -->
                <div class="glass p-8 card-hover">
                    <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Acadêmico Elite</h3>
                    <p class="text-white/50 leading-relaxed">Boletins inteligentes, controle de frequência, lançamento de notas simplificado e cronograma dinâmico de aulas.</p>
                </div>

                <!-- Financeiro -->
                <div class="glass p-8 card-hover">
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Poder Financeiro</h3>
                    <p class="text-white/50 leading-relaxed">Conciliação bancária via OFX, emissão de faturas, relatórios DRE em tempo real e gestão de planos de contas.</p>
                </div>

                <!-- CRM -->
                <div class="glass p-8 card-hover">
                    <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">CRM & Captação</h3>
                    <p class="text-white/50 leading-relaxed">Kanban de interessados focado em conversão, histórico de prospecção e funil de vendas integrado à secretaria.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Mobile Section -->
    <section id="mobile" class="py-24 px-6 relative overflow-hidden">
        <div class="container mx-auto grid md:grid-cols-2 gap-16 items-center">
            <div class="order-2 md:order-1 flex justify-center">
                <img src="/images/landing/mobile.png" alt="Torre360 App" class="w-full max-w-sm drop-shadow-[0_0_50px_rgba(59,130,246,0.2)]">
            </div>
            <div class="order-1 md:order-2 space-y-8">
                <h2 class="text-4xl md:text-5xl font-bold">Experiência Mobile sem barreiras</h2>
                <div class="space-y-6 text-white/60">
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-accent/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-accent" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path></svg>
                        </div>
                        <p><strong class="text-white">Notificações Push:</strong> Avisos de documentos e faturas direto no celular.</p>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-accent/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-accent" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path></svg>
                        </div>
                        <p><strong class="text-white">Acesso Biométrico:</strong> Segurança e velocidade no login administrativo.</p>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-accent/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-accent" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path></svg>
                        </div>
                        <p><strong class="text-white">Tabelas Inteligentes:</strong> Experiência otimizada para telas pequenas.</p>
                    </div>
                </div>
                <div class="pt-4">
                    <button class="px-8 py-3 rounded-full border border-white/20 text-sm font-bold flex items-center gap-3 hover:bg-white/5">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.523 15.3414C17.7061 15.1118 17.8152 14.8252 17.8152 14.512C17.8152 13.9184 17.3888 13.4391 16.8288 13.3514C16.9205 13.1292 16.9698 12.8851 16.9698 12.6288C16.9698 11.5361 16.084 10.6503 14.9912 10.6503C14.7826 10.6503 14.5835 10.6826 14.3963 10.7424C14.2144 9.6953 13.3032 8.9058 12.2033 8.9058C11.6669 8.9058 11.1818 9.1171 10.8242 9.4601C10.4632 8.3563 9.4261 7.5613 8.2033 7.5613C6.7303 7.5613 5.5361 8.7554 5.5361 10.2285C5.5361 10.4285 5.5583 10.623 5.6006 10.8101C4.6644 11.2335 4.0203 12.181 4.0203 13.2848C4.0203 14.28 4.5458 15.1522 5.3315 15.6457L17.523 15.3414Z"></path></svg>
                        Solicitar App Android
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Seguranca Section -->
    <section id="seguranca" class="py-24 px-6">
        <div class="container mx-auto">
            <div class="glass p-12 relative overflow-hidden">
                <div class="absolute right-0 bottom-0 opacity-10 translate-x-1/4 translate-y-1/4">
                    <svg class="w-96 h-96" fill="currentColor" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 6c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 12.27c-2.53-.36-4.83-1.55-6.62-3.32.32-.23 1.1-.64 2-.95.84-.23 1.4-.2 1.83-.16 1.48.16 2.52.16 4 0 .43-.04.41.07 1.25.3 1.05.28 1.95.73 2.15.93-1.79 1.77-4.09 2.96-6.61 3.32v-.12z"></path></svg>
                </div>
                <div class="max-w-2xl space-y-6 relative z-10">
                    <h2 class="text-4xl font-bold">Auditoria e Controle de Classe Mundial</h2>
                    <p class="text-white/60 leading-relaxed">Não se preocupe com a quem tem acesso a quê. Através do sistema Shield, você define permissões cirúrgicas para cada membro da sua equipe.</p>
                    <ul class="space-y-4">
                        <li class="flex items-center gap-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
                            <span class="text-white/80">Logs completos de todas as ações sensíveis.</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
                            <span class="text-white/80">Gestão de permissões baseada em papéis (RBAC).</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-20 px-6 border-t border-white/5 text-center">
        <div class="container mx-auto space-y-8">
            <span class="text-3xl font-bold gradient-text">Torre360</span>
            <div class="flex justify-center gap-8 text-white/50 text-sm">
                <a href="#" class="hover:text-white transition-colors">Termos</a>
                <a href="#" class="hover:text-white transition-colors">Privacidade</a>
                <a href="#" class="hover:text-white transition-colors">Suporte</a>
            </div>
            <p class="text-white/30 text-xs">Copyright © 2026 Torre360 — Todos os direitos reservados.</p>
        </div>
    </footer>

    <!-- Reveal on Scroll Script -->
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>
