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
                    eventDisplay: 'block',
                    dayMaxEvents: true,
                    eventContent: (info) => {
                        const p = info.event.extendedProps;
                        const statusColor = p.is_agendado ? '#10b981' : '#6b7280';
                        const containerBg = p.is_agendado ? '#f0fdf4' : '#f9fafb';
                        const textColor = 'white';
                        
                        return {
                            html: `
                                <div style='border: 2px solid ${statusColor}; border-radius: 4px; padding: 2px; display: flex; flex-direction: column; gap: 2px; background: ${containerBg}; overflow: hidden;'>
                                    <div style='background: ${statusColor}; color: ${textColor}; padding: 1px 4px; border-radius: 2px; font-size: 8px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>
                                        ${p.is_agendado ? 'AGENDADO' : 'DISPONÍVEL'}
                                    </div>
                                    <div style='color: #1f2937; padding: 1px 4px; font-size: 9px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>
                                        ${p.is_agendado ? p.aluno_nome : 'Feriado/Livre'}
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
                    const selectedProfessores = this.filters?.professores || [];
                    const selectedStatus = this.filters?.status || null;

                    const matchProfessor = selectedProfessores.length === 0 || selectedProfessores.includes(String(event.professor_id));
                    
                    let matchStatus = true;
                    if (selectedStatus === 'agendado') {
                        matchStatus = event.is_agendado === true;
                    } else if (selectedStatus === 'disponivel') {
                        matchStatus = event.is_agendado === false;
                    }
                    
                    return matchProfessor && matchStatus;
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
                <div class="tooltip-card" :style="`border-left-color: ${tooltip.event?.is_agendado ? '#10b981' : '#6b7280'}`">
                    <div class="tooltip-header" :style="`background-color: ${tooltip.event?.is_agendado ? '#10b981' : '#6b7280'}20; border-bottom-color: ${tooltip.event?.is_agendado ? '#10b981' : '#6b7280'}40`">
                        <span x-text="tooltip.event?.is_agendado ? 'Detalhes da Preceptoria' : 'Horário Disponível'"></span>
                    </div>
                    <div class="tooltip-body">
                         <div class="tooltip-item">
                            <div class="p-1.5 rounded mr-2" style="background-color: #3b82f620"><svg class="w-3 h-3" style="color: #3b82f6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></div>
                            <strong>Professor:</strong> <span x-text="tooltip.event?.professor_nome"></span>
                        </div>
                        
                        <template x-if="tooltip.event?.is_agendado">
                            <div class="tooltip-item">
                                <div class="p-1.5 rounded mr-2" style="background-color: #10b98120"><svg class="w-3 h-3" style="color: #10b981" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg></div>
                                <strong>Aluno:</strong> <span x-text="tooltip.event?.aluno_nome"></span>
                            </div>
                        </template>

                        <div class="tooltip-item" style="margin-top: 10px;">
                            <div class="p-1.5 rounded mr-2" style="background-color: #f59e0b20"><svg class="w-3 h-3" style="color: #f59e0b" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                            <strong>Horário:</strong> <span x-text="`${tooltip.event?.hora_inicio} - ${tooltip.event?.hora_fim}`"></span>
                        </div>

                         <div class="tooltip-item">
                            <div class="p-1.5 rounded mr-2" style="background-color: #6b728020"><svg class="w-3 h-3" style="color: #6b7280" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>
                            <strong>Data:</strong> <span x-text="tooltip.event?.data"></span>
                        </div>
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
                width: 280px;
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
        </style>
    </div>
</x-filament-widgets::widget>
