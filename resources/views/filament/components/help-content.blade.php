@php
    $video = $video ?? null;
    $icone = $icone ?? null;
    $titulo = $titulo ?? null;
    $resumo = $resumo ?? null;
@endphp
<style>
    .help-modal { --h-accent: #243468; --h-accent-soft: rgba(36, 52, 104, .08); --h-border: rgba(120, 120, 140, .25); font-size: .925rem; line-height: 1.55; }
    .dark .help-modal { --h-accent: #93a7ff; --h-accent-soft: rgba(147, 167, 255, .12); }
    .help-hero { display: flex; gap: 1rem; align-items: center; padding: 1rem 1.25rem; border-radius: .9rem; margin-bottom: 1rem; background: linear-gradient(135deg, var(--h-accent-soft), transparent); border: 1px solid var(--h-border); }
    .help-hero-emoji { flex: none; width: 3.5rem; height: 3.5rem; display: grid; place-items: center; font-size: 1.9rem; border-radius: 50%; background: var(--h-accent-soft); border: 2px solid var(--h-accent); }
    .help-hero h2 { margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--h-accent); }
    .help-hero p { margin: .15rem 0 0; opacity: .8; }
    .help-modal h3 { margin: 1.25rem 0 .5rem; padding-bottom: .3rem; font-size: 1.02rem; font-weight: 700; color: var(--h-accent); border-bottom: 2px solid var(--h-accent-soft); }
    .help-modal p { margin: .35rem 0; }
    .help-modal ul, .help-modal ol { margin: .4rem 0; padding-left: 1.25rem; }
    .help-modal li { margin: .3rem 0; }
    .help-modal li strong, .help-modal p strong { color: var(--h-accent); }
    .help-modal ul.help-list, .help-modal ol.help-steps { list-style: none; padding: 0; display: grid; gap: .5rem; }
    .help-item, .help-step { display: flex; gap: .75rem; align-items: flex-start; padding: .6rem .8rem; border: 1px solid var(--h-border); border-radius: .7rem; background: var(--h-accent-soft); }
    .help-item-emoji { flex: none; font-size: 1.35rem; line-height: 1.4; }
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
