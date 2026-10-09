@php
    /** @var \App\Filament\Resources\Interessados\RelationManagers\TimelineRelationManager $livewire */
    $metricas = $livewire->metricas;
    $eventos = $livewire->timeline;
    $tiposContato = $livewire->tiposContato;

    $telefonePessoa = preg_replace('/\D/', '', $metricas['telefone_contato'] ?? '');
    $telefoneWhatsapp = (! empty($telefonePessoa) && strlen($telefonePessoa) <= 11 && ! str_starts_with($telefonePessoa, '55'))
        ? '55'.$telefonePessoa
        : $telefonePessoa;

    [$tomTemperatura, $iconeTemperatura] = match (mb_strtolower((string) $metricas['temperatura'])) {
        'quente' => ['rose', '🔥'],
        'frio' => ['sky', '❄️'],
        default => ['amber', '🌤️'],
    };

    $proximo = $metricas['proximo_contato_em'];
    $emAtraso = (bool) $metricas['esta_em_atraso'];
    $nps = $metricas['nps_visita'];
    $tomNps = $nps ? ($nps['nota'] >= 9 ? 'emerald' : ($nps['nota'] >= 7 ? 'amber' : 'rose')) : 'gray';

    $filtrando = $livewire->filtroCategoria !== 'todos' || filled($livewire->termoBusca);

    $categorias = [
        'todos' => ['label' => 'Todos', 'icone' => 'heroicon-m-squares-2x2', 'total' => null],
        'contatos' => ['label' => 'Contatos & Mensagens', 'icone' => 'heroicon-m-chat-bubble-left-right', 'total' => $metricas['total_contatos']],
        'visitas' => ['label' => 'Visitas & NPS', 'icone' => 'heroicon-m-academic-cap', 'total' => $metricas['total_visitas']],
        'documentos' => ['label' => 'Documentos & IA', 'icone' => 'heroicon-m-document-text', 'total' => $metricas['total_documentos']],
        'etapas' => ['label' => 'Etapas & Funil', 'icone' => 'heroicon-m-arrows-right-left', 'total' => null],
    ];

    // Cor do badge Filament a partir das cores (Tailwind ou semânticas) devolvidas pelo serviço.
    $corBadge = fn (?string $cor): string => match ($cor) {
        'emerald', 'success' => 'success',
        'amber', 'warning' => 'warning',
        'rose', 'danger' => 'danger',
        'info', 'blue', 'sky' => 'info',
        'primary' => 'primary',
        default => 'gray',
    };

    $rotuloDia = function (\Carbon\CarbonInterface $dia): string {
        if ($dia->isToday()) {
            return 'Hoje';
        }
        if ($dia->isYesterday()) {
            return 'Ontem';
        }
        if ($dia->isTomorrow()) {
            return 'Amanhã';
        }

        return \Illuminate\Support\Str::ucfirst($dia->copy()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY'));
    };

    // Paginação progressiva: o feed mostra um lote por vez e o botão "Carregar mais" soma outro.
    $totalEventos = $eventos->count();
    $eventosVisiveis = $eventos->take($livewire->limiteEventos);
    $eventosRestantes = $totalEventos - $eventosVisiveis->count();

    $dias = $eventosVisiveis->groupBy(fn (array $evento): string => $evento['data_hora']->format('Y-m-d'));

    $atalhosRetorno = [
        'Amanhã' => now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'),
        'Em 3 dias' => now()->addDays(3)->setTime(9, 0)->format('Y-m-d\TH:i'),
        'Em 1 semana' => now()->addWeek()->setTime(9, 0)->format('Y-m-d\TH:i'),
    ];
@endphp

<div class="tl360">
    <style>
        .tl360 {
            --tl-surface: #fff;
            --tl-sunken: var(--gray-50);
            --tl-border: var(--gray-200);
            --tl-text: var(--gray-950);
            --tl-muted: var(--gray-500);
            --tl-faint: var(--gray-400);
            --tl-accent: #243468;
            --tl-accent-on: #fff;
            --tl-on: #fff;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            font-size: .875rem;
            line-height: 1.45;
            color: var(--tl-text);
        }
        .dark .tl360 {
            --tl-surface: var(--gray-900);
            --tl-sunken: rgba(255, 255, 255, .04);
            --tl-border: rgba(255, 255, 255, .1);
            --tl-text: #fff;
            --tl-muted: var(--gray-400);
            --tl-faint: var(--gray-500);
            --tl-accent: #93a7ff;
            --tl-accent-on: #0f172a;
            --tl-on: #0f172a;
        }

        /* Tons semânticos: cada elemento recebe --tl-c (cor), --tl-bg (fundo suave) e --tl-bd (borda) */
        .tl360-tone-emerald { --tl-rgb: 5 150 105; }
        .tl360-tone-sky { --tl-rgb: 2 132 199; }
        .tl360-tone-purple { --tl-rgb: 147 51 234; }
        .tl360-tone-amber { --tl-rgb: 217 119 6; }
        .tl360-tone-indigo { --tl-rgb: 79 70 229; }
        .tl360-tone-teal { --tl-rgb: 13 148 136; }
        .tl360-tone-violet { --tl-rgb: 124 58 237; }
        .tl360-tone-blue { --tl-rgb: 37 99 235; }
        .tl360-tone-rose { --tl-rgb: 225 29 72; }
        .tl360-tone-gray { --tl-rgb: 100 116 139; }
        .dark .tl360-tone-emerald { --tl-rgb: 52 211 153; }
        .dark .tl360-tone-sky { --tl-rgb: 56 189 248; }
        .dark .tl360-tone-purple { --tl-rgb: 192 132 252; }
        .dark .tl360-tone-amber { --tl-rgb: 251 191 36; }
        .dark .tl360-tone-indigo { --tl-rgb: 129 140 248; }
        .dark .tl360-tone-teal { --tl-rgb: 45 212 191; }
        .dark .tl360-tone-violet { --tl-rgb: 167 139 250; }
        .dark .tl360-tone-blue { --tl-rgb: 96 165 250; }
        .dark .tl360-tone-rose { --tl-rgb: 251 113 133; }
        .dark .tl360-tone-gray { --tl-rgb: 148 163 184; }
        [class*="tl360-tone-"] {
            --tl-c: rgb(var(--tl-rgb));
            --tl-bg: rgb(var(--tl-rgb) / .12);
            --tl-bd: rgb(var(--tl-rgb) / .3);
        }

        .tl360 svg.fi-icon { width: 1rem; height: 1rem; flex: none; }
        .tl360-card {
            background: var(--tl-surface);
            border: 1px solid var(--tl-border);
            border-radius: .875rem;
            box-shadow: 0 1px 2px rgb(16 24 40 / .05);
        }
        .tl360-cq { container-type: inline-size; }

        /* ---------- 1. Cabeçalho + indicadores ---------- */
        .tl360-hero { padding: 1.125rem 1.25rem 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
        .tl360-hero-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem 1rem; }
        .tl360-id { display: flex; align-items: center; gap: .875rem; min-width: 0; }
        .tl360-logo {
            flex: none; width: 3rem; height: 3rem; border-radius: .875rem; display: grid; place-items: center;
            color: #fff; font-weight: 800; font-size: .95rem; letter-spacing: -.02em;
            background: linear-gradient(135deg, #243468, #3d58b0);
            box-shadow: 0 8px 16px -8px rgb(36 52 104 / .7);
        }
        .tl360-title { font-size: 1.0625rem; font-weight: 700; letter-spacing: -.01em; color: var(--tl-text); }
        .tl360-sub { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem .625rem; margin-top: .25rem; font-size: .8125rem; color: var(--tl-muted); }
        .tl360-sub strong { font-weight: 600; color: var(--tl-text); }
        .tl360-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }

        .tl360-kpis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .625rem; }
        @container (min-width: 34rem) { .tl360-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @container (min-width: 60rem) { .tl360-kpis { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
        .tl360-kpi {
            display: flex; align-items: flex-start; gap: .625rem; min-width: 0; padding: .75rem .875rem;
            background: var(--tl-sunken); border: 1px solid var(--tl-border); border-radius: .75rem;
        }
        .tl360-kpi.is-alert { background: var(--tl-bg); border-color: var(--tl-bd); }
        .tl360-kpi-ico { flex: none; display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: .5rem; background: var(--tl-bg); color: var(--tl-c); font-size: 1rem; }
        .tl360-kpi-ico svg.fi-icon { color: var(--tl-c); width: 1.125rem; height: 1.125rem; }
        .tl360-kpi-body { min-width: 0; }
        .tl360-kpi-label { font-size: .6875rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--tl-muted); }
        .tl360-kpi-value { font-size: 1.125rem; font-weight: 700; line-height: 1.3; font-variant-numeric: tabular-nums; color: var(--tl-text); white-space: nowrap; }
        .tl360-kpi.is-alert .tl360-kpi-value { color: var(--tl-c); }
        .tl360-kpi-hint { overflow: hidden; font-size: .75rem; color: var(--tl-muted); text-overflow: ellipsis; white-space: nowrap; }
        @container (max-width: 33.99rem) { .tl360-kpi { padding: .625rem .75rem; } .tl360-kpi-ico { display: none; } }

        /* ---------- 2. Registro rápido ---------- */
        .tl360-compose-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .875rem 1.125rem; }
        .tl360-compose-id { display: flex; align-items: center; gap: .75rem; min-width: 0; }
        .tl360-compose-ico { flex: none; display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: .625rem; background: var(--tl-bg); color: var(--tl-c); }
        .tl360-compose-ico svg.fi-icon { color: var(--tl-c); width: 1.25rem; height: 1.25rem; }
        .tl360-h4 { font-size: .9375rem; font-weight: 650; color: var(--tl-text); }
        .tl360-desc { font-size: .8125rem; color: var(--tl-muted); }
        .tl360-compose-body { display: flex; flex-direction: column; gap: .875rem; padding: 0 1.125rem 1.125rem; border-top: 1px solid var(--tl-border); padding-top: 1rem; }
        .tl360-label { display: block; margin-bottom: .375rem; font-size: .8125rem; font-weight: 500; color: var(--tl-text); }
        .tl360-chans { display: flex; flex-wrap: wrap; gap: .5rem; }
        .tl360-chan {
            display: inline-flex; align-items: center; gap: .4375rem; padding: .4375rem .875rem; cursor: pointer;
            font-size: .8125rem; font-weight: 500; color: var(--tl-text);
            background: var(--tl-surface); border: 1px solid var(--tl-border); border-radius: 999px;
            transition: background-color .15s, border-color .15s, color .15s, box-shadow .15s;
        }
        .tl360-chan svg.fi-icon { color: var(--tl-c); }
        .tl360-chan:hover { background: var(--tl-bg); border-color: var(--tl-bd); }
        .tl360-chan.is-active { color: var(--tl-on); background: var(--tl-c); border-color: var(--tl-c); box-shadow: 0 4px 10px -4px var(--tl-c); }
        .tl360-chan.is-active svg.fi-icon { color: var(--tl-on); }
        .tl360-fields { display: grid; grid-template-columns: minmax(0, 1fr); gap: .875rem; }
        @container (min-width: 40rem) { .tl360-fields { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .tl360-quick { display: flex; flex-wrap: wrap; gap: .375rem; margin-top: .375rem; }
        .tl360-pill {
            padding: .125rem .5rem; cursor: pointer; font-size: .75rem; font-weight: 500; color: var(--tl-accent);
            background: transparent; border: 1px dashed var(--tl-border); border-radius: 999px; transition: background-color .15s, border-color .15s;
        }
        .tl360-pill:hover { background: var(--tl-sunken); border-color: var(--tl-accent); }
        .tl360-submit { display: flex; align-items: center; justify-content: flex-end; }
        .tl360-error { margin-top: .25rem; font-size: .75rem; color: var(--danger-600); }
        .dark .tl360-error { color: var(--danger-400); }

        /* ---------- 3. Filtros e busca ---------- */
        .tl360-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .625rem .875rem; }
        .tl360-filters { display: flex; flex-wrap: wrap; gap: .375rem; }
        .tl360-filter {
            display: inline-flex; flex: none; align-items: center; gap: .375rem; padding: .375rem .75rem; cursor: pointer;
            font-size: .8125rem; font-weight: 500; color: var(--tl-muted);
            background: var(--tl-surface); border: 1px solid var(--tl-border); border-radius: 999px;
            transition: background-color .15s, border-color .15s, color .15s;
        }
        .tl360-filter:hover { color: var(--tl-text); border-color: var(--tl-faint); }
        .tl360-filter svg.fi-icon { color: currentColor; }
        .tl360-filter.is-active { color: var(--tl-accent-on); background: var(--tl-accent); border-color: var(--tl-accent); }
        .tl360-count {
            min-width: 1.25rem; padding: 0 .375rem; font-size: .6875rem; font-weight: 700; line-height: 1.25rem; text-align: center;
            background: rgb(127 127 127 / .16); border-radius: 999px;
        }
        .tl360-filter.is-active .tl360-count { background: color-mix(in srgb, var(--tl-accent-on) 22%, transparent); }
        .tl360-search { flex: 1 1 16rem; min-width: 14rem; }
        .tl360-search-row { display: flex; align-items: center; gap: .25rem; width: 100%; }
        .tl360-search-row .fi-input { flex: 1; min-width: 0; }
        .tl360-clear { display: grid; place-items: center; flex: none; width: 1.5rem; height: 1.5rem; margin-inline-end: .375rem; cursor: pointer; color: var(--tl-faint); background: transparent; border: 0; border-radius: 999px; }
        .tl360-clear:hover { color: var(--tl-text); background: var(--tl-sunken); }
        .tl360-summary { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .8125rem; color: var(--tl-muted); }
        .tl360-link { padding: 0; cursor: pointer; font-size: .8125rem; font-weight: 600; color: var(--tl-accent); background: none; border: 0; }
        .tl360-link:hover { text-decoration: underline; }

        /* ---------- 4. Feed ---------- */
        .tl360-feed { position: relative; display: flex; flex-direction: column; gap: .875rem; padding-top: .25rem; transition: opacity .15s; }
        .tl360-feed.is-loading { opacity: .55; }
        .tl360-feed::before {
            content: ""; position: absolute; top: .75rem; bottom: .75rem; left: calc(1.25rem - 1px); width: 2px; border-radius: 2px;
            background: var(--tl-border);
        }
        .tl360-day { position: relative; display: flex; align-items: center; gap: .75rem; margin-top: .5rem; }
        .tl360-day:first-child { margin-top: 0; }
        .tl360-day-dot { flex: none; display: grid; place-items: center; width: 2.5rem; }
        .tl360-day-dot i { width: .75rem; height: .75rem; background: var(--tl-surface); border: 2px solid var(--tl-faint); border-radius: 999px; }
        .tl360-day.is-today .tl360-day-dot i { background: var(--tl-accent); border-color: var(--tl-accent); }
        .tl360-day-label {
            display: inline-flex; align-items: center; gap: .5rem; padding: .25rem .75rem; font-size: .75rem; font-weight: 650; color: var(--tl-muted);
            background: var(--tl-surface); border: 1px solid var(--tl-border); border-radius: 999px;
        }
        .tl360-day.is-today .tl360-day-label { color: var(--tl-accent); border-color: var(--tl-accent); }
        .tl360-day-flag { padding: 0 .375rem; font-size: .625rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--tl-c); background: var(--tl-bg); border-radius: 999px; }

        .tl360-item { position: relative; display: grid; grid-template-columns: 2.5rem minmax(0, 1fr); gap: .75rem; align-items: start; }
        .tl360-node {
            position: relative; display: grid; place-items: center; width: 2.5rem; height: 2.5rem; color: var(--tl-c);
            background: linear-gradient(var(--tl-bg), var(--tl-bg)), var(--tl-surface);
            border: 1px solid var(--tl-bd); border-radius: .75rem; transition: transform .15s;
        }
        .tl360-node svg.fi-icon { width: 1.25rem; height: 1.25rem; color: var(--tl-c); }
        .tl360-item:hover .tl360-node { transform: scale(1.06); }
        .tl360-ev {
            container-type: inline-size; padding: .875rem 1rem; background: var(--tl-surface); border: 1px solid var(--tl-border);
            border-radius: .875rem; box-shadow: 0 1px 2px rgb(16 24 40 / .05); transition: border-color .15s, box-shadow .15s;
        }
        .tl360-item:hover .tl360-ev { border-color: var(--tl-bd); box-shadow: 0 8px 20px -10px rgb(16 24 40 / .25); }
        .tl360-ev-head { display: flex; flex-direction: column; gap: .25rem; }
        @container (min-width: 30rem) {
            .tl360-ev-head { flex-direction: row; align-items: flex-start; justify-content: space-between; gap: 1rem; }
            .tl360-when { text-align: right; }
        }
        .tl360-ev-title { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem .5rem; font-size: .9375rem; font-weight: 650; line-height: 1.35; color: var(--tl-text); }
        .tl360-ev-sub { margin-top: .125rem; font-size: .8125rem; color: var(--tl-muted); }
        .tl360-when { flex: none; line-height: 1.3; }
        .tl360-when strong { display: block; font-size: .8125rem; font-weight: 650; font-variant-numeric: tabular-nums; color: var(--tl-text); }
        .tl360-when span { font-size: .75rem; color: var(--tl-faint); }

        .tl360-note {
            margin-top: .625rem; padding: .625rem .75rem; overflow-wrap: anywhere; white-space: pre-line;
            background: var(--tl-sunken); border-left: 3px solid var(--tl-c); border-radius: .25rem .625rem .625rem .25rem;
        }
        .tl360-tags { display: flex; flex-wrap: wrap; gap: .375rem; margin-top: .625rem; }
        .tl360-tag {
            display: inline-flex; align-items: center; gap: .25rem; padding: .125rem .5rem; font-size: .75rem; color: var(--tl-muted);
            background: var(--tl-sunken); border: 1px solid var(--tl-border); border-radius: 999px;
        }
        .tl360-tag svg.fi-icon { width: .875rem; height: .875rem; }
        .tl360-flow { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: .625rem; }
        .tl360-flow svg.fi-icon { color: var(--tl-faint); }
        .tl360-step { padding: .25rem .625rem; font-size: .8125rem; font-weight: 600; color: var(--tl-muted); background: var(--tl-sunken); border: 1px solid var(--tl-border); border-radius: .5rem; }
        .tl360-step.is-to { color: var(--tl-c); background: var(--tl-bg); border-color: var(--tl-bd); }

        .tl360-panel { margin-top: .75rem; padding: .75rem .875rem; background: var(--tl-bg); border: 1px solid var(--tl-bd); border-radius: .75rem; }
        .tl360-panel-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; }
        .tl360-panel-title { display: inline-flex; align-items: center; gap: .375rem; font-size: .8125rem; font-weight: 700; color: var(--tl-c); }
        .tl360-panel-title svg.fi-icon { color: var(--tl-c); }
        .tl360-panel-text { margin-top: .5rem; font-size: .8125rem; color: var(--tl-text); }
        .tl360-nps { display: flex; flex-wrap: wrap; align-items: center; gap: .875rem 1.25rem; margin-top: .625rem; }
        .tl360-nps-score {
            display: grid; flex: none; place-items: center; width: 3.5rem; height: 3.5rem; line-height: 1; color: var(--tl-c);
            background: var(--tl-surface); border: 2px solid var(--tl-c); border-radius: 999px;
        }
        .tl360-nps-score b { font-size: 1.25rem; font-weight: 800; }
        .tl360-nps-score small { margin-top: -.5rem; font-size: .625rem; font-weight: 600; opacity: .8; }
        .tl360-rates { display: grid; flex: 1 1 14rem; grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr)); gap: .5rem .875rem; }
        .tl360-rate-label { display: block; font-size: .6875rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; color: var(--tl-muted); }
        .tl360-stars { display: flex; align-items: center; gap: .0625rem; margin-top: .125rem; }
        .tl360-stars svg.fi-icon { width: .9375rem; height: .9375rem; color: var(--tl-faint); opacity: .45; }
        .tl360-stars svg.fi-icon.is-on { color: #f59e0b; opacity: 1; }
        .tl360-stars em { margin-left: .375rem; font-size: .75rem; font-style: normal; font-weight: 700; color: var(--tl-text); }
        .tl360-quote { margin: .625rem 0 0; padding-top: .5rem; font-style: italic; color: var(--tl-text); border-top: 1px dashed var(--tl-bd); }
        .tl360-meter { display: inline-flex; align-items: center; gap: .5rem; font-size: .75rem; font-weight: 700; color: var(--tl-c); }
        .tl360-meter-bar { width: 4.5rem; height: .375rem; overflow: hidden; background: rgb(127 127 127 / .2); border-radius: 999px; }
        .tl360-meter-bar i { display: block; height: 100%; background: var(--tl-c); border-radius: 999px; }
        .tl360-dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: .375rem .875rem; margin: .625rem 0 0; padding-top: .5rem; border-top: 1px dashed var(--tl-bd); }
        .tl360-dl dt { font-size: .6875rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; color: var(--tl-muted); }
        .tl360-dl dd { margin: 0; overflow-wrap: anywhere; font-size: .8125rem; font-weight: 600; color: var(--tl-text); }
        .tl360-warn { display: flex; align-items: flex-start; gap: .5rem; margin-top: .625rem; padding: .5rem .625rem; font-size: .8125rem; font-weight: 500; color: var(--tl-c); background: linear-gradient(var(--tl-bg), var(--tl-bg)), var(--tl-surface); border: 1px solid var(--tl-bd); border-radius: .625rem; }
        .tl360-warn svg.fi-icon { margin-top: .125rem; color: var(--tl-c); }
        .tl360-cta { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .625rem .875rem; margin-top: .75rem; padding: .625rem .75rem; font-size: .8125rem; color: var(--tl-text); background: var(--tl-bg); border: 1px solid var(--tl-bd); border-radius: .75rem; }
        .tl360-cta-actions { display: flex; flex-wrap: wrap; gap: .5rem; }

        .tl360-more { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: .25rem 0 .5rem; font-size: .8125rem; color: var(--tl-muted); }
        .tl360-empty { padding: 2.5rem 1.25rem; text-align: center; background: var(--tl-surface); border: 1px dashed var(--tl-border); border-radius: .875rem; }
        .tl360-empty-ico { display: inline-grid; place-items: center; width: 3.25rem; height: 3.25rem; margin-bottom: .75rem; color: var(--tl-c); background: var(--tl-bg); border-radius: 1rem; }
        .tl360-empty-ico svg.fi-icon { width: 1.5rem; height: 1.5rem; color: var(--tl-c); }
        .tl360-empty p { max-width: 28rem; margin: .25rem auto 1rem; font-size: .8125rem; color: var(--tl-muted); }

        @media (max-width: 640px) {
            .tl360-hero { padding: 1rem; }
            .tl360-filters { flex-wrap: nowrap; width: 100%; overflow-x: auto; padding-bottom: .25rem; scrollbar-width: thin; }
            .tl360-search { flex-basis: 100%; }
            .tl360-feed::before { left: calc(1rem - 1px); }
            .tl360-item { grid-template-columns: 2rem minmax(0, 1fr); gap: .5rem; }
            .tl360-node { width: 2rem; height: 2rem; border-radius: .625rem; }
            .tl360-node svg.fi-icon { width: 1rem; height: 1rem; }
            .tl360-day-dot { width: 2rem; }
            .tl360-ev { padding: .75rem; }
        }
    </style>

    {{-- ============ 1. CABEÇALHO E INDICADORES 360° ============ --}}
    <section class="tl360-card tl360-hero">
        <div class="tl360-hero-top">
            <div class="tl360-id">
                <div class="tl360-logo" aria-hidden="true">360°</div>
                <div class="min-w-0">
                    <h3 class="tl360-title">Linha do Tempo Omnichannel</h3>
                    <p class="tl360-sub">
                        <span>Consultor: <strong>{{ $metricas['consultor_nome'] }}</strong></span>
                        <span aria-hidden="true">•</span>
                        <span>Status: <x-filament::badge :color="$metricas['status_cor'] ?: 'primary'" size="sm">{{ $metricas['status_nome'] }}</x-filament::badge></span>
                    </p>
                </div>
            </div>

            <div class="tl360-actions">
                @if(! empty($telefoneWhatsapp))
                    <x-filament::button
                        tag="a"
                        :href="'https://wa.me/'.$telefoneWhatsapp"
                        target="_blank"
                        rel="noopener noreferrer"
                        color="success"
                        size="sm"
                        icon="heroicon-m-chat-bubble-left-ellipsis"
                    >
                        Retornar no WhatsApp
                    </x-filament::button>
                @endif

                {{ ($livewire->ajudaAction)(['class' => 'cursor-pointer']) }}
            </div>
        </div>

        <div class="tl360-cq">
            <div class="tl360-kpis">
                <div class="tl360-kpi tl360-tone-{{ $tomTemperatura }}">
                    <span class="tl360-kpi-ico" aria-hidden="true">{{ $iconeTemperatura }}</span>
                    <div class="tl360-kpi-body">
                        <div class="tl360-kpi-label">Temperatura</div>
                        <div class="tl360-kpi-value">{{ ucfirst((string) $metricas['temperatura']) }}</div>
                        <div class="tl360-kpi-hint">Termômetro comercial</div>
                    </div>
                </div>

                <div class="tl360-kpi tl360-tone-indigo">
                    <span class="tl360-kpi-ico"><x-filament::icon icon="heroicon-m-star" /></span>
                    <div class="tl360-kpi-body">
                        <div class="tl360-kpi-label">Lead Score</div>
                        <div class="tl360-kpi-value">{{ $metricas['lead_score'] }} <span class="tl360-desc">pts</span></div>
                        <div class="tl360-kpi-hint">{{ $metricas['total_interacoes'] }} interações no total</div>
                    </div>
                </div>

                <div class="tl360-kpi tl360-tone-{{ $emAtraso ? 'rose' : 'emerald' }} {{ $emAtraso ? 'is-alert' : '' }}" @if($proximo) title="{{ $proximo->format('d/m/Y H:i') }}" @endif>
                    <span class="tl360-kpi-ico"><x-filament::icon :icon="$emAtraso ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-calendar-days'" /></span>
                    <div class="tl360-kpi-body">
                        <div class="tl360-kpi-label">{{ $emAtraso ? 'Retorno em atraso' : 'Próximo contato' }}</div>
                        <div class="tl360-kpi-value">{{ $proximo ? $proximo->format('d/m H:i') : '—' }}</div>
                        <div class="tl360-kpi-hint">{{ $proximo ? $proximo->diffForHumans() : 'Nenhum retorno agendado' }}</div>
                    </div>
                </div>

                <div class="tl360-kpi tl360-tone-sky">
                    <span class="tl360-kpi-ico"><x-filament::icon icon="heroicon-m-chat-bubble-left-right" /></span>
                    <div class="tl360-kpi-body">
                        <div class="tl360-kpi-label">Último contato</div>
                        <div class="tl360-kpi-value">{{ $metricas['ultimo_contato_em'] ? $metricas['ultimo_contato_em']->format('d/m H:i') : '—' }}</div>
                        <div class="tl360-kpi-hint">{{ $metricas['ultimo_contato_relativo'] ?? 'Nenhum contato registrado' }}</div>
                    </div>
                </div>

                <div class="tl360-kpi tl360-tone-{{ $tomNps }}">
                    <span class="tl360-kpi-ico"><x-filament::icon icon="heroicon-m-face-smile" /></span>
                    <div class="tl360-kpi-body">
                        <div class="tl360-kpi-label">NPS da visita</div>
                        <div class="tl360-kpi-value">{{ $nps ? $nps['nota'].'/10' : '—' }}</div>
                        <div class="tl360-kpi-hint">{{ $nps ? $nps['classificacao'] : 'Sem pesquisa respondida' }}</div>
                    </div>
                </div>

                <div class="tl360-kpi tl360-tone-violet">
                    <span class="tl360-kpi-ico"><x-filament::icon icon="heroicon-m-document-check" /></span>
                    <div class="tl360-kpi-body">
                        <div class="tl360-kpi-label">Documentos</div>
                        <div class="tl360-kpi-value">{{ $metricas['total_documentos'] > 0 ? $metricas['docs_aprovados'].'/'.$metricas['total_documentos'] : '—' }}</div>
                        <div class="tl360-kpi-hint">{{ $metricas['total_documentos'] > 0 ? 'verificados • '.$metricas['docs_com_ia'].' com IA' : 'Nenhum documento enviado' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ 2. REGISTRO RÁPIDO DE INTERAÇÃO ============ --}}
    @if($livewire->podeRegistrar)
    <section class="tl360-card tl360-cq tl360-tone-indigo">
        <div class="tl360-compose-head">
            <div class="tl360-compose-id">
                <span class="tl360-compose-ico"><x-filament::icon icon="heroicon-m-pencil-square" /></span>
                <div class="min-w-0">
                    <h4 class="tl360-h4">Registrar nova interação</h4>
                    <p class="tl360-desc">Grave o contato e o Lead Score é recalculado na hora.</p>
                </div>
            </div>

            <x-filament::button
                type="button"
                color="gray"
                size="sm"
                :icon="$livewire->mostrarFormularioRapido ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down'"
                icon-position="after"
                wire:click="toggleFormularioRapido"
            >
                {{ $livewire->mostrarFormularioRapido ? 'Recolher' : 'Expandir' }}
            </x-filament::button>
        </div>

        @if($livewire->mostrarFormularioRapido)
            <form wire:submit="registrarContatoRapido" class="tl360-compose-body">
                <div>
                    <span class="tl360-label">Canal da interação</span>
                    <div class="tl360-chans" role="group" aria-label="Canal da interação">
                        @foreach($tiposContato as $tipo)
                            @php
                                $slug = mb_strtolower($tipo->nome);
                                [$iconeCanal, $tomCanal] = match (true) {
                                    str_contains($slug, 'whatsapp') => ['heroicon-m-chat-bubble-left-ellipsis', 'emerald'],
                                    str_contains($slug, 'liga') || str_contains($slug, 'telef') => ['heroicon-m-phone', 'sky'],
                                    str_contains($slug, 'mail') => ['heroicon-m-envelope', 'purple'],
                                    str_contains($slug, 'presen') => ['heroicon-m-user-group', 'amber'],
                                    default => ['heroicon-m-chat-bubble-bottom-center-text', 'indigo'],
                                };
                                $selecionado = (int) $livewire->novoTipoContatoId === (int) $tipo->id;
                            @endphp
                            <button
                                type="button"
                                wire:key="tl-canal-{{ $tipo->id }}"
                                wire:click="$set('novoTipoContatoId', {{ $tipo->id }})"
                                aria-pressed="{{ $selecionado ? 'true' : 'false' }}"
                                class="tl360-chan tl360-tone-{{ $tomCanal }} {{ $selecionado ? 'is-active' : '' }}"
                            >
                                <x-filament::icon :icon="$iconeCanal" />
                                {{ $tipo->nome }}
                            </button>
                        @endforeach
                    </div>
                    @error('novoTipoContatoId') <p class="tl360-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="tl360-relato" class="tl360-label">O que foi conversado ou acordado com a família?</label>
                    <x-filament::input.wrapper class="fi-fo-textarea">
                        <textarea
                            id="tl360-relato"
                            wire:model="novoRelato"
                            rows="3"
                            required
                            placeholder="Ex.: Família adorou os laboratórios de robótica e pediu a simulação da anuidade do 7º ano. Combinado retorno na terça…"
                        ></textarea>
                    </x-filament::input.wrapper>
                    @error('novoRelato') <p class="tl360-error">{{ $message }}</p> @enderror
                </div>

                <div class="tl360-fields">
                    <div>
                        <label for="tl360-resultado" class="tl360-label">Resultado</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="tl360-resultado" wire:model="novoResultado">
                                <option value="retornar">Retornar depois</option>
                                <option value="agendou_visita">Agendou visita</option>
                                <option value="matriculou">Efetuou matrícula</option>
                                <option value="sem_interesse">Sem interesse</option>
                                <option value="outro">Outro</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label for="tl360-retorno" class="tl360-label">Agendar próximo retorno</label>
                        <x-filament::input.wrapper>
                            <x-filament::input id="tl360-retorno" type="datetime-local" wire:model="novaDataProximoContato" />
                        </x-filament::input.wrapper>
                        <div class="tl360-quick">
                            @foreach($atalhosRetorno as $rotulo => $valor)
                                <button type="button" class="tl360-pill" wire:click="$set('novaDataProximoContato', '{{ $valor }}')">{{ $rotulo }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label for="tl360-duracao" class="tl360-label">Duração</label>
                        <x-filament::input.wrapper suffix="min">
                            <x-filament::input id="tl360-duracao" type="number" min="1" placeholder="Ex.: 15" wire:model="novaDuracaoMinutos" />
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div class="tl360-submit">
                    <x-filament::button type="submit" icon="heroicon-m-bolt" wire:target="registrarContatoRapido">
                        Gravar interação e recalcular score
                    </x-filament::button>
                </div>
            </form>
        @endif
    </section>
    @endif

    {{-- ============ 3. FILTROS E BUSCA ============ --}}
    <div class="tl360-toolbar">
        <div class="tl360-filters" role="group" aria-label="Filtrar eventos por categoria">
            @foreach($categorias as $chave => $info)
                <button
                    type="button"
                    wire:key="tl-filtro-{{ $chave }}"
                    wire:click="filtrar('{{ $chave }}')"
                    aria-pressed="{{ $livewire->filtroCategoria === $chave ? 'true' : 'false' }}"
                    class="tl360-filter {{ $livewire->filtroCategoria === $chave ? 'is-active' : '' }}"
                >
                    <x-filament::icon :icon="$info['icone']" />
                    {{ $info['label'] }}
                    @if($info['total'] !== null)
                        <span class="tl360-count">{{ $info['total'] }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="tl360-search">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <div class="tl360-search-row">
                    <x-filament::input type="search" wire:model.live.debounce.300ms="termoBusca" placeholder="Buscar palavra ou relato…" aria-label="Buscar na linha do tempo" />
                    @if(filled($livewire->termoBusca))
                        <button type="button" class="tl360-clear" wire:click="$set('termoBusca', '')" aria-label="Limpar busca">
                            <x-filament::icon icon="heroicon-m-x-mark" />
                        </button>
                    @endif
                </div>
            </x-filament::input.wrapper>
        </div>
    </div>

    @if($filtrando && $eventos->isNotEmpty())
        <div class="tl360-summary">
            <span>{{ $eventos->count() }} {{ $eventos->count() === 1 ? 'evento encontrado' : 'eventos encontrados' }}</span>
            <span aria-hidden="true">•</span>
            <button type="button" class="tl360-link" wire:click="limparFiltros">Limpar filtros</button>
        </div>
    @endif

    {{-- ============ 4. FEED DA LINHA DO TEMPO ============ --}}
    @if($eventos->isEmpty())
        <div class="tl360-empty tl360-tone-indigo">
            <span class="tl360-empty-ico"><x-filament::icon :icon="$filtrando ? 'heroicon-o-magnifying-glass' : 'heroicon-o-clock'" /></span>
            <h4 class="tl360-h4">{{ $filtrando ? 'Nenhum evento encontrado' : 'A jornada ainda não começou' }}</h4>
            <p>
                {{ $filtrando
                    ? 'Não há interações para a categoria ou o termo de busca selecionado. Tente outro filtro.'
                    : 'Nenhum contato, visita ou documento registrado para este interessado. Use o formulário acima para gravar a primeira interação.' }}
            </p>
            @if($filtrando)
                <x-filament::button type="button" color="gray" size="sm" wire:click="limparFiltros">Ver todos os eventos</x-filament::button>
            @endif
        </div>
    @else
        <div class="tl360-feed" wire:loading.class="is-loading" wire:target="filtrar, termoBusca, limparFiltros">
            @foreach($dias as $chaveDia => $eventosDoDia)
                @php
                    $dia = $eventosDoDia->first()['data_hora'];
                    $diaFuturo = $dia->copy()->startOfDay()->isFuture();
                @endphp

                <div class="tl360-day tl360-tone-blue {{ $dia->isToday() ? 'is-today' : '' }}" wire:key="tl-dia-{{ $chaveDia }}">
                    <span class="tl360-day-dot" aria-hidden="true"><i></i></span>
                    <span class="tl360-day-label">
                        {{ $rotuloDia($dia) }}
                        @if($diaFuturo)
                            <span class="tl360-day-flag">Agendado</span>
                        @endif
                    </span>
                </div>

                @foreach($eventosDoDia as $evento)
                    @php $det = $evento['detalhes'] ?? []; @endphp

                    <article class="tl360-item tl360-tone-{{ $evento['tom'] ?? 'indigo' }}" wire:key="tl-ev-{{ $evento['id'] }}">
                        <div class="tl360-node" aria-hidden="true">
                            <x-filament::icon :icon="$evento['icone']" />
                        </div>

                        <div class="tl360-ev">
                            <header class="tl360-ev-head">
                                <div class="min-w-0">
                                    <div class="tl360-ev-title">
                                        <span>{{ $evento['titulo'] }}</span>
                                        @if(! empty($evento['badge']))
                                            <x-filament::badge :color="$corBadge($evento['badge_cor'] ?? null)" size="sm">{{ $evento['badge'] }}</x-filament::badge>
                                        @endif
                                    </div>
                                    @if(! empty($evento['subtitulo']))
                                        <p class="tl360-ev-sub">{{ $evento['subtitulo'] }}</p>
                                    @endif
                                </div>

                                <div class="tl360-when" title="{{ $evento['data_formatada'] }} • Registro #{{ $evento['registro_id'] }}">
                                    <strong>{{ $evento['data_hora']->format('H:i') }}</strong>
                                    <span>{{ $evento['data_relativa'] }}</span>
                                </div>
                            </header>

                            {{-- Etapa do funil: de → para --}}
                            @if($evento['tipo'] === 'etapa' && ! empty($det['status_novo']))
                                <div class="tl360-flow">
                                    @if(! empty($det['status_anterior']))
                                        <span class="tl360-step">{{ $det['status_anterior'] }}</span>
                                        <x-filament::icon icon="heroicon-m-arrow-long-right" />
                                    @else
                                        <span class="tl360-desc">Status inicial</span>
                                    @endif
                                    <span class="tl360-step is-to">{{ $det['status_novo'] }}</span>
                                </div>
                            @elseif(! empty($evento['conteudo']))
                                <div class="tl360-note">{{ $evento['conteudo'] }}</div>
                            @endif

                            {{-- Contato: duração --}}
                            @if($evento['tipo'] === 'contato' && ! empty($det['duracao_minutos']))
                                <div class="tl360-tags">
                                    <span class="tl360-tag"><x-filament::icon icon="heroicon-m-clock" /> {{ $det['duracao_minutos'] }} min de conversa</span>
                                </div>
                            @endif

                            {{-- Visita: avaliação pós-tour (NPS) --}}
                            @if($evento['tipo'] === 'visita' && ! empty($det))
                                @if($det['pesquisa_respondida'])
                                    @php
                                        $tomVisitaNps = $det['nota_nps'] >= 9 ? 'emerald' : ($det['nota_nps'] >= 7 ? 'amber' : 'rose');
                                        $dimensoes = [
                                            'Atendimento' => $det['nota_atendimento'],
                                            'Infraestrutura' => $det['nota_infraestrutura'],
                                            'Pedagógico' => $det['nota_proposta_pedagogica'],
                                        ];
                                    @endphp
                                    <div class="tl360-panel tl360-tone-teal">
                                        <div class="tl360-panel-head">
                                            <span class="tl360-panel-title"><x-filament::icon icon="heroicon-m-star" /> Avaliação pós-tour</span>
                                            <x-filament::badge :color="$corBadge($tomVisitaNps)" size="sm">{{ $det['classificacao_nps'] }}</x-filament::badge>
                                        </div>

                                        <div class="tl360-nps">
                                            <div class="tl360-nps-score tl360-tone-{{ $tomVisitaNps }}" title="Nota NPS">
                                                <b>{{ $det['nota_nps'] }}</b>
                                                <small>/10</small>
                                            </div>

                                            <div class="tl360-rates">
                                                @foreach($dimensoes as $dimensao => $nota)
                                                    <div>
                                                        <span class="tl360-rate-label">{{ $dimensao }}</span>
                                                        <div class="tl360-stars" aria-label="{{ $dimensao }}: {{ $nota ?? 'sem nota' }} de 5">
                                                            @for($i = 1; $i <= 5; $i++)
                                                                <x-filament::icon icon="heroicon-s-star" :class="$i <= (int) $nota ? 'is-on' : ''" />
                                                            @endfor
                                                            <em>{{ $nota ?? '—' }}</em>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        @if(! empty($det['comentario_pesquisa']))
                                            <blockquote class="tl360-quote">“{{ $det['comentario_pesquisa'] }}”</blockquote>
                                        @endif
                                    </div>
                                @elseif($det['status'] === 'realizada')
                                    <div class="tl360-cta tl360-tone-amber">
                                        <span>Pesquisa de satisfação pós-tour ainda não foi preenchida pela família.</span>
                                        <div class="tl360-cta-actions">
                                            @if(! empty($det['link_whatsapp']))
                                                <x-filament::button tag="a" :href="$det['link_whatsapp']" target="_blank" rel="noopener noreferrer" color="success" size="xs" icon="heroicon-m-paper-airplane">Enviar por WhatsApp</x-filament::button>
                                            @endif
                                            @if(! empty($det['link_pesquisa']))
                                                <x-filament::button tag="a" :href="$det['link_pesquisa']" target="_blank" rel="noopener noreferrer" color="gray" size="xs" icon="heroicon-m-arrow-top-right-on-square">Abrir pesquisa</x-filament::button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endif

                            {{-- Documento: parecer de IA --}}
                            @if($evento['tipo'] === 'documento' && ! empty($det))
                                @if($det['tem_analise_ia'])
                                    <div class="tl360-panel tl360-tone-violet">
                                        <div class="tl360-panel-head">
                                            <span class="tl360-panel-title"><x-filament::icon icon="heroicon-m-sparkles" /> Parecer Gemini Vision OCR</span>
                                            @if(! empty($det['score_confianca']))
                                                <span class="tl360-meter" title="Confiança da análise">
                                                    <span class="tl360-meter-bar"><i style="width: {{ min(100, max(0, (int) $det['score_confianca'])) }}%"></i></span>
                                                    {{ $det['score_confianca'] }}%
                                                </span>
                                            @endif
                                        </div>

                                        @if(! empty($det['resumo_ia']))
                                            <p class="tl360-panel-text">{{ $det['resumo_ia'] }}</p>
                                        @endif

                                        @php
                                            $dadosExtraidos = collect($det['dados_extraidos'] ?? [])->filter(fn ($valor) => ! is_array($valor) && ! blank($valor));
                                        @endphp
                                        @if($dadosExtraidos->isNotEmpty())
                                            <dl class="tl360-dl">
                                                @foreach($dadosExtraidos as $campo => $valor)
                                                    <div>
                                                        <dt>{{ ucfirst(str_replace('_', ' ', (string) $campo)) }}</dt>
                                                        <dd>{{ $valor }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @endif

                                        @if(! empty($det['alertas']))
                                            <div class="tl360-warn tl360-tone-amber">
                                                <x-filament::icon icon="heroicon-m-exclamation-triangle" />
                                                <span><strong>Divergências:</strong> {{ implode(' • ', $det['alertas']) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="tl360-tags">
                                        <span class="tl360-tag"><x-filament::icon icon="heroicon-m-sparkles" /> Sem análise de IA</span>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </article>
                @endforeach
            @endforeach
        </div>

        <div class="tl360-more">
            @if($eventosRestantes > 0)
                <span>Mostrando {{ $eventosVisiveis->count() }} de {{ $totalEventos }} eventos</span>
                <x-filament::button
                    type="button"
                    color="gray"
                    size="sm"
                    icon="heroicon-m-arrow-down"
                    wire:click="carregarMais"
                    wire:target="carregarMais"
                >
                    Carregar mais {{ min($livewire::EVENTOS_POR_LOTE, $eventosRestantes) }}
                </x-filament::button>
            @else
                <span>{{ $totalEventos === 1 ? '1 evento' : $totalEventos.' eventos' }} • início do histórico</span>
            @endif
        </div>
    @endif
</div>
