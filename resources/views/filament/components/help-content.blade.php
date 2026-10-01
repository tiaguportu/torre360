@php
    $video = $video ?? null;
    $icone = $icone ?? null;
    $titulo = $titulo ?? null;
    $resumo = $resumo ?? null;

    // Ajudas antigas (HTML sem emoji): enriquece automaticamente com emoji, cores e resumo em destaque.
    $legado = ! \App\Support\HelpEnricher::jaEnriquecido($content);
    $enriquecido = \App\Support\HelpEnricher::enriquecer($content);
    $content = $enriquecido['html'];
    if ($legado && ! $titulo && ! $icone) {
        $icone = '💡';
        $titulo = 'Guia rápido desta tela';
        $resumo = $enriquecido['resumo'];
    }
@endphp
<style>
    .help-modal { --h-accent: #243468; --h-accent-soft: rgba(36, 52, 104, .08); --h-border: rgba(120, 120, 140, .25); font-size: .925rem; line-height: 1.55; }
    .dark .help-modal { --h-accent: #93a7ff; --h-accent-soft: rgba(147, 167, 255, .12); }

    /* paleta por seção: cada bloco ganha uma cor própria */
    .help-modal .help-c0 { --h-accent: #2563eb; --h-accent-soft: rgba(37, 99, 235, .09); --h-line: rgba(37, 99, 235, .45); }
    .help-modal .help-c1 { --h-accent: #059669; --h-accent-soft: rgba(5, 150, 105, .09); --h-line: rgba(5, 150, 105, .45); }
    .help-modal .help-c2 { --h-accent: #d97706; --h-accent-soft: rgba(217, 119, 6, .10); --h-line: rgba(217, 119, 6, .5); }
    .help-modal .help-c3 { --h-accent: #7c3aed; --h-accent-soft: rgba(124, 58, 237, .09); --h-line: rgba(124, 58, 237, .45); }
    .help-modal .help-c4 { --h-accent: #db2777; --h-accent-soft: rgba(219, 39, 119, .09); --h-line: rgba(219, 39, 119, .45); }
    .help-modal .help-c5 { --h-accent: #0891b2; --h-accent-soft: rgba(8, 145, 178, .09); --h-line: rgba(8, 145, 178, .45); }
    .dark .help-modal .help-c0 { --h-accent: #7aa2ff; --h-accent-soft: rgba(122, 162, 255, .13); }
    .dark .help-modal .help-c1 { --h-accent: #34d399; --h-accent-soft: rgba(52, 211, 153, .12); }
    .dark .help-modal .help-c2 { --h-accent: #fbbf24; --h-accent-soft: rgba(251, 191, 36, .12); }
    .dark .help-modal .help-c3 { --h-accent: #a78bfa; --h-accent-soft: rgba(167, 139, 250, .13); }
    .dark .help-modal .help-c4 { --h-accent: #f472b6; --h-accent-soft: rgba(244, 114, 182, .12); }
    .dark .help-modal .help-c5 { --h-accent: #22d3ee; --h-accent-soft: rgba(34, 211, 238, .12); }

    .help-sec { margin: 1.1rem 0; }
    .help-hero { display: flex; gap: 1rem; align-items: center; padding: 1rem 1.25rem; border-radius: .9rem; margin-bottom: 1rem; background: linear-gradient(135deg, var(--h-accent-soft), transparent); border: 1px solid var(--h-border); }
    .help-hero-emoji { flex: none; width: 3.5rem; height: 3.5rem; display: grid; place-items: center; font-size: 1.9rem; border-radius: 50%; background: var(--h-accent-soft); border: 2px solid var(--h-accent); }
    .help-hero h2 { margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--h-accent); }
    .help-hero p { margin: .15rem 0 0; opacity: .85; }
    .help-modal h3 { margin: 0 0 .55rem; padding: .35rem .7rem; font-size: 1.02rem; font-weight: 700; color: var(--h-accent); background: var(--h-accent-soft); border-left: 4px solid var(--h-accent); border-radius: .45rem; }
    .help-modal p { margin: .35rem 0; }
    .help-modal ul, .help-modal ol { margin: .4rem 0; padding-left: 1.25rem; }
    .help-modal li { margin: .3rem 0; }
    .help-modal li strong, .help-modal p strong { color: var(--h-accent); }
    .help-modal ul.help-list, .help-modal ol.help-steps { list-style: none; padding: 0; display: grid; gap: .5rem; }
    .help-modal ul.help-sublist { list-style: none; padding-left: .25rem; margin: .35rem 0 0; }
    .help-modal ul.help-sublist li { margin: .2rem 0; }
    .help-item, .help-step { display: flex; gap: .75rem; align-items: flex-start; padding: .6rem .8rem; border: 1px solid var(--h-border); border-left: 4px solid var(--h-accent); border-radius: .7rem; background: var(--h-accent-soft); }
    .help-item-emoji { flex: none; font-size: 1.35rem; line-height: 1.4; }
    .help-item > div { min-width: 0; }
    .help-item p { margin: .1rem 0 0; opacity: .85; }
    .help-step-n { flex: none; width: 1.6rem; height: 1.6rem; display: grid; place-items: center; border-radius: 50%; font-weight: 700; font-size: .8rem; color: #fff; background: var(--h-accent); }
    .dark .help-step-n { color: #111; }
    .help-callout { display: flex; gap: .65rem; align-items: flex-start; margin: .9rem 0; padding: .7rem .9rem; border-radius: .7rem; border: 1px solid; }
    .help-callout > span { font-size: 1.25rem; line-height: 1.3; }
    .help-callout p { margin: 0; }
    .help-tip { background: rgba(16, 185, 129, .1); border-color: rgba(16, 185, 129, .45); }
    .help-warn { background: rgba(245, 158, 11, .12); border-color: rgba(245, 158, 11, .5); }
    .help-modal blockquote { margin: .9rem 0; padding: .6rem .9rem; border-left: 4px solid var(--h-accent); border-radius: .4rem; background: var(--h-accent-soft); font-style: normal; }
    .help-figure { margin: 1rem 0; text-align: center; }
    .help-figure img { max-width: 100%; border-radius: .7rem; border: 1px solid var(--h-border); }
    .help-figure figcaption { margin-top: .3rem; font-size: .8rem; opacity: .7; }
</style>
<div class="help-modal max-w-none">
    @if($icone || $titulo)
        <div class="help-hero">
            @if($icone)<div class="help-hero-emoji">{{ $icone }}</div>@endif
            <div>
                @if($titulo)<h2>{{ $titulo }}</h2>@endif
                @if($resumo)<p>{{ $resumo }}</p>@endif
            </div>
        </div>
    @endif

    @if($video)
        <div class="not-prose mb-4">
            @include('filament.components.video-player', ['video' => $video])
        </div>
    @endif

    {!! $content !!}
</div>
