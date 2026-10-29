@php
    $podeEditar = (bool) auth()->user()?->can('Update:Preceptoria');
    $podeCriar = (bool) auth()->user()?->can('Create:Preceptoria');
@endphp

<x-filament-panels::page>
    {{ $this->form }}

    <div class="prec-cal" wire:ignore>
        <div class="prec-cal-barra">
            <span class="prec-cal-legenda"><i style="background:#6b7280"></i> Livre</span>
            <span class="prec-cal-legenda"><i style="background:#059669"></i> Agendada</span>
            <span class="prec-cal-legenda"><i style="background:#fecaca"></i> Feriado / dia não letivo</span>
            <label class="prec-cal-fds">
                <input type="checkbox" data-fins-de-semana checked>
                Mostrar fins de semana
            </label>
        </div>

        @if ($podeEditar)
            <p class="prec-cal-dica">
                Arraste uma preceptoria para mudar o dia ou o horário; estique a borda inferior para mudar a duração.
                Clique nela para trocar o professor ou o aluno.
                @if ($podeCriar)
                    Clique e arraste num espaço vazio (semana ou dia) para criar um horário.
                @endif
            </p>
        @endif

        <div data-calendario></div>
        <p class="prec-cal-erro" data-erro hidden>
            Não foi possível carregar o calendário. Verifique a conexão com a internet e recarregue a página.
        </p>
    </div>

    @script
        <script>
            const raiz = $wire.$el;
            const el = raiz.querySelector('[data-calendario]');
            const erro = raiz.querySelector('[data-erro]');
            const podeEditar = @js($podeEditar);
            const podeCriar = @js($podeCriar);
            const base = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11';

            const injetar = (id, src) => {
                if (document.getElementById(id)) return;
                const s = document.createElement('script');
                s.id = id;
                s.src = src;
                document.head.appendChild(s);
            };

            const esperar = (pronto, limiteMs) => new Promise((resolve) => {
                const inicio = Date.now();
                const verificar = () => {
                    if (pronto()) return resolve(true);
                    if (Date.now() - inicio > limiteMs) return resolve(false);
                    setTimeout(verificar, 50);
                };
                verificar();
            });

            const temLocale = () => (window.FullCalendar?.globalLocales ?? []).some((l) => l.code === 'pt-br');

            const iniciar = async () => {
                if (typeof FullCalendar === 'undefined') injetar('fullcalendar-script', `${base}/index.global.min.js`);

                if (!(await esperar(() => typeof FullCalendar !== 'undefined', 15000))) {
                    erro.hidden = false;
                    return;
                }

                injetar('fullcalendar-locale-ptbr', `${base}/locales/pt-br.global.min.js`);
                await esperar(temLocale, 5000);

                const reagendar = async (info) => {
                    const p = info.event.extendedProps;

                    if (p.agendado && !window.confirm(
                        `Esta preceptoria já está agendada com ${p.aluno}.\n` +
                        'O aluno, os responsáveis e o professor serão avisados do novo horário. Continuar?'
                    )) {
                        info.revert();
                        return;
                    }

                    let resposta;
                    try {
                        resposta = await $wire.reagendarPreceptoria(
                            Number(info.event.id),
                            info.event.startStr.slice(0, 19),
                            info.event.end ? info.event.endStr.slice(0, 19) : null,
                        );
                    } catch (e) {
                        resposta = { ok: false };
                    }

                    if (!resposta || !resposta.ok) {
                        info.revert();
                        return;
                    }

                    calendario.refetchEvents();
                };

                const calendario = new FullCalendar.Calendar(el, {
                    locale: temLocale() ? 'pt-br' : 'en',
                    initialView: 'timeGridWeek',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
                    },
                    height: 'auto',
                    allDaySlot: false,
                    nowIndicator: true,
                    slotMinTime: '06:00:00',
                    slotMaxTime: '22:00:00',
                    slotDuration: '00:30:00',
                    snapDuration: '00:15:00',
                    slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                    dayMaxEvents: true,
                    editable: podeEditar,
                    selectable: podeCriar,
                    selectMirror: true,
                    selectMinDistance: 6,
                    events: (info, sucesso, falha) => {
                        $wire.getEventos(info.startStr.slice(0, 10), info.endStr.slice(0, 10)).then(sucesso).catch(falha);
                    },
                    eventContent: (arg) => {
                        const p = arg.event.extendedProps;

                        // faixas de feriado/dia não letivo e a visão em lista usam o desenho padrão
                        if (p.especial || arg.view.type.startsWith('list')) return undefined;

                        const bloco = document.createElement('div');
                        bloco.className = 'prec-evt';

                        const linhas = [arg.timeText, p.professor, p.agendado ? p.aluno : 'Livre'];
                        linhas.forEach((texto, i) => {
                            const linha = document.createElement('div');
                            linha.className = i === 0 ? 'prec-evt-hora' : 'prec-evt-linha';
                            linha.textContent = texto ?? '';
                            bloco.appendChild(linha);
                        });

                        return { domNodes: [bloco] };
                    },
                    eventDidMount: (info) => {
                        const p = info.event.extendedProps;

                        info.el.title = p.especial
                            ? p.motivo
                            : [p.professor, p.agendado ? `Aluno: ${p.aluno}` : 'Horário livre', p.horario, p.ciclo ? `Ciclo: ${p.ciclo}` : null]
                                .filter(Boolean).join('\n');
                    },
                    eventDrop: reagendar,
                    eventResize: reagendar,
                    eventClick: (info) => {
                        const p = info.event.extendedProps;
                        if (p.especial || !p.url) return;

                        info.jsEvent.preventDefault();

                        // quem edita troca professor/aluno no modal (que tem o link para o registro completo)
                        if (p.gerenciavel) {
                            $wire.mountAction('gerenciarPreceptoria', { id: Number(info.event.id) });
                            return;
                        }

                        window.location.href = p.url;
                    },
                    select: (info) => {
                        const data = info.startStr.slice(0, 10);
                        const comHora = info.startStr.length > 10;

                        $wire.mountAction('criarPreceptoria', {
                            data,
                            hora_inicio: comHora ? info.startStr.slice(11, 16) : null,
                            hora_fim: comHora && info.endStr.slice(0, 10) === data ? info.endStr.slice(11, 16) : null,
                        });

                        calendario.unselect();
                    },
                });

                calendario.render();

                raiz.querySelector('[data-fins-de-semana]').addEventListener('change', (e) => {
                    calendario.setOption('weekends', e.target.checked);
                });

                $wire.on('preceptorias-recarregar', (evento) => {
                    const dados = Array.isArray(evento) ? (evento[0] ?? {}) : (evento ?? {});

                    if (dados.irPara) calendario.gotoDate(dados.irPara);
                    calendario.refetchEvents();
                });
            };

            iniciar();
        </script>
    @endscript

    <style>
        .prec-cal { background: #fff; border: 1px solid rgba(120, 120, 140, .25); border-radius: .75rem; padding: 1rem; }
        .dark .prec-cal { background: rgba(255, 255, 255, .03); }
        .prec-cal-barra { display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; align-items: center; font-size: .8125rem; margin-bottom: .5rem; }
        .prec-cal-legenda { display: inline-flex; align-items: center; gap: .375rem; }
        .prec-cal-legenda i { width: .75rem; height: .75rem; border-radius: .2rem; display: inline-block; }
        .prec-cal-fds { margin-left: auto; display: inline-flex; align-items: center; gap: .375rem; cursor: pointer; }
        .prec-cal-dica { font-size: .8125rem; opacity: .75; margin-bottom: .75rem; }
        .prec-cal-erro { color: #b91c1c; font-size: .875rem; }
        .prec-evt { padding: 1px 3px; line-height: 1.25; overflow: hidden; font-size: .75rem; cursor: pointer; }
        .prec-evt-hora { font-weight: 600; }
        .prec-evt-linha { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fc { --fc-bg-event-opacity: .55; --fc-today-bg-color: rgba(59, 130, 246, .08); }
        .fc .fc-toolbar-title { font-size: 1.1rem; font-weight: 600; text-transform: capitalize; }
        .fc .fc-button-primary { background-color: #3b82f6; border-color: #3b82f6; text-transform: capitalize; }
        .fc .fc-button-primary:not(:disabled).fc-button-active { background-color: #1d4ed8; border-color: #1d4ed8; }
        .fc .fc-bg-event .fc-event-title { font-size: .65rem; font-style: normal; padding: 2px 4px; color: #7f1d1d; }
        .dark .fc { --fc-border-color: rgba(255, 255, 255, .12); --fc-page-bg-color: transparent; --fc-neutral-bg-color: rgba(255, 255, 255, .05); --fc-list-event-hover-bg-color: rgba(255, 255, 255, .06); --fc-bg-event-opacity: .3; }
        .dark .fc .fc-bg-event .fc-event-title { color: #fecaca; }
    </style>
</x-filament-panels::page>
