<div>
<x-filament-widgets::widget>
    <div 
        x-data="{
            calendar: null,
            allEvents: @js($this->getAllEvents()),
            filters: @entangle('data'),
            tooltip: {
                show: false,
                x: 0,
                y: 0,
                event: null
            },
            init() {
                this.loadResources().then(() => {
                    this.waitForFullCalendar();
                });
                this.$watch('filters', () => this.filterEvents());
            },
            async loadResources() {
                if (typeof FullCalendar !== 'undefined') return;

                if (!document.getElementById('fullcalendar-script')) {
                    const script = document.createElement('script');
                    script.id = 'fullcalendar-script';
                    script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js';
                    document.head.appendChild(script);
                }
            },
            waitForFullCalendar() {
                if (typeof FullCalendar !== 'undefined') {
                    this.setupCalendar();
                    return;
                }
                setTimeout(() => this.waitForFullCalendar(), 50);
            },
            setupCalendar() {
                this.calendar = new FullCalendar.Calendar(this.$refs.calendar, {
                    initialView: 'dayGridMonth',
                    locale: 'pt-br',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    events: this.allEvents,
                    eventDisplay: 'block', // Force blocks instead of dots
                    dayMaxEvents: false,
                    eventContent: (info) => {
                        const p = info.event.extendedProps;
                        const hasContent = p.conteudo_ministrado_full && p.conteudo_ministrado_full.trim().length > 0;
                        const statusColor = hasContent ? '#10b981' : '#6b7280'; // success (green) vs primary (gray)
                        
                        return {
                            html: `
                                <div style='border: 1px solid ${statusColor}; border-radius: 4px; padding: 2px; display: flex; flex-direction: column; gap: 2px; background: white; overflow: hidden;'>
                                    <div style='background: ${p.turma_cor}; color: white; padding: 1px 4px; border-radius: 2px; font-size: 8px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>
                                        ${p.turma_nome}
                                    </div>
                                    <div style='background: ${p.disciplina_cor}; color: white; padding: 1px 4px; border-radius: 2px; font-size: 8px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>
                                        ${p.disciplina_nome}
                                    </div>
                                    <div style='background: #9ca3af; color: white; padding: 1px 4px; border-radius: 2px; font-size: 8px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>
                                        ${p.professor_nome}
                                    </div>
                                </div>
                            `
                        };
                    },
                    eventMouseEnter: (info) => {
                        this.tooltip.event = info.event.extendedProps;
                        this.tooltip.event.title = info.event.title;
                        this.tooltip.show = true;
                        this.tooltip.x = info.jsEvent.clientX;
                        this.tooltip.y = info.jsEvent.clientY;
                    },
                    eventMouseLeave: () => {
                        this.tooltip.show = false;
                    },
                    eventClick: (info) => {
                        if (info.event.extendedProps.url) {
                            window.location.href = info.event.extendedProps.url;
                            info.jsEvent.preventDefault();
                        }
                    }
                });
                this.calendar.render();
            },
            filterEvents() {
                if (!this.calendar) return;
                const filtered = this.allEvents.filter(event => {
                    const matchTurma = !this.filters.turmas || this.filters.turmas.length === 0 || this.filters.turmas.includes(event.turma_id.toString());
                    const matchDisciplina = !this.filters.disciplinas || this.filters.disciplinas.length === 0 || this.filters.disciplinas.includes(event.disciplina_id.toString());
                    const matchProfessor = !this.filters.professores || this.filters.professores.length === 0 || this.filters.professores.includes(event.professor_id.toString());
                    
                    return matchTurma && matchDisciplina && matchProfessor;
                });
                this.calendar.removeAllEvents();
                this.calendar.addEventSource(filtered);
            }
        }"
        @mousemove.window="if(tooltip.show) { tooltip.x = $event.clientX; tooltip.y = $event.clientY; }"
        class="space-y-6"
    >
        {{ $this->form }}

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-white/10 p-4" style="position: relative;">
            <div x-ref="calendar" wire:ignore style="min-height: 500px;"></div>

            <!-- Custom Tooltip Vanilla CSS -->
            <div 
                x-show="tooltip.show"
                x-cloak
                class="hover-tooltip-container"
                :style="`left: ${tooltip.x + 15}px; top: ${tooltip.y + 15}px; display: ${tooltip.show ? 'block' : 'none'};`"
            >
                <div class="tooltip-card" :style="`border-left-color: ${tooltip.event?.disciplina_cor || '#3b82f6'}`">
                    <div class="tooltip-header" :style="`background-color: ${tooltip.event?.disciplina_cor}20; border-bottom-color: ${tooltip.event?.disciplina_cor}40`">
                        <span x-text="tooltip.event?.disciplina_nome"></span>
                    </div>
                    <div class="tooltip-body">
                         <div class="tooltip-item">
                            <div class="p-1.5 rounded mr-2" :style="`background-color: ${tooltip.event?.curso_cor}20`"><svg class="w-3 h-3" :style="`color: ${tooltip.event?.curso_cor}`" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg></div>
                            <strong>Curso:</strong> <span x-text="tooltip.event?.curso_nome"></span>
                        </div>
                        <div class="tooltip-item">
                             <div class="p-1.5 rounded mr-2" :style="`background-color: ${tooltip.event?.turma_cor}20`"><svg class="w-3 h-3" :style="`color: ${tooltip.event?.turma_cor}`" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></div>
                            <strong>Turma:</strong> <span x-text="tooltip.event?.turma_nome"></span>
                        </div>
                        <div class="tooltip-item">
                            <div class="p-1.5 rounded mr-2" :style="`background-color: ${tooltip.event?.disciplina_cor}20`"><svg class="w-3 h-3" :style="`color: ${tooltip.event?.disciplina_cor}`" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></div>
                            <strong>Professor:</strong> <span x-text="tooltip.event?.professor_nome"></span>
                        </div>
                        <div class="tooltip-item">
                            <div class="p-1.5 rounded mr-2" style="background-color: #f59e0b20"><svg class="w-3 h-3" style="color: #f59e0b" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                            <strong>Início:</strong> <span x-text="tooltip.event?.hora_inicio"></span>
                        </div>
                        
                        <template x-if="tooltip.event?.conteudo_ministrado_full || tooltip.event?.conteudo_ministrado">
                            <div class="tooltip-content">
                                <hr :style="`border-top-color: ${tooltip.event?.disciplina_cor}30`">
                                <div class="content-label" :style="`color: ${tooltip.event?.disciplina_cor}80`">CONTEÚDO MINISTRADO</div>
                                <div class="content-text" :style="`border-left: 2px solid ${tooltip.event?.disciplina_cor}40`" x-text="tooltip.event?.conteudo_ministrado_full || tooltip.event?.conteudo_ministrado"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <style>
            [x-cloak] { display: none !important; }
            .fc-event { cursor: pointer; border: none !important; padding: 1px 0 !important; background: transparent !important; }
            .fc-event-main { padding: 0 !important; }
            .fc-toolbar-title { font-size: 1.1em !important; font-weight: bold; text-transform: capitalize; }
            .fc-button-primary { background-color: #3b82f6 !important; border-color: #3b82f6 !important; text-transform: capitalize; }
            
            .hover-tooltip-container {
                position: fixed;
                z-index: 999999;
                pointer-events: none;
                transition: opacity 0.2s ease;
            }

            .tooltip-card {
                background: #ffffff;
                color: #1f2937;
                border: 1px solid #e5e7eb;
                border-left: 5px solid #3b82f6;
                border-radius: 8px;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                width: 320px;
                overflow: hidden;
                font-family: sans-serif;
            }

            .dark .tooltip-card {
                background: #1f2937;
                color: #f3f4f6;
                border-color: #374151;
            }

            .tooltip-header {
                padding: 10px 15px;
                font-weight: bold;
                border-bottom: 1px solid #f1f5f9;
                font-size: 14px;
            }

            .tooltip-body { padding: 15px; }

            .tooltip-item {
                margin-bottom: 8px;
                font-size: 13px;
                display: flex;
                align-items: center;
            }

            .tooltip-item strong {
                color: #6b7280;
                min-width: 70px;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .content-label {
                font-size: 10px;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .content-text {
                padding: 10px;
                border-radius: 4px;
                font-style: italic;
                line-height: 1.5;
                font-size: 12.5px;
                background: #f8fafc;
            }
            .dark .content-text { background: #111827; }
        </style>
    </div>
</x-filament-widgets::widget>
</div>
