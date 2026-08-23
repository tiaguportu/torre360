<x-filament-panels::page>
    <div
        x-data="{
            calendar: null,
            allEvents: @js($this->getEvents()),
            init() {
                this.loadResources().then(() => {
                    this.waitForFullCalendar();
                });
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
                        right: 'dayGridMonth,listMonth'
                    },
                    events: this.allEvents,
                    eventDisplay: 'block',
                    dayMaxEvents: true,
                    eventDidMount: (info) => {
                        const p = info.event.extendedProps;
                        info.el.title = p.turma ? `${p.tipo} — ${p.turma}` : p.tipo;
                    }
                });
                this.calendar.render();
            }
        }"
        class="space-y-6"
    >
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-white/10 p-4">
            <div x-ref="calendar" wire:ignore style="min-height: 500px;"></div>
        </div>

        <style>
            .fc-event { cursor: default; }
            .fc-toolbar-title { font-size: 1.1em !important; font-weight: bold; text-transform: capitalize; }
            .fc-button-primary { background-color: #3b82f6 !important; border-color: #3b82f6 !important; text-transform: capitalize; }
        </style>
    </div>
</x-filament-panels::page>
