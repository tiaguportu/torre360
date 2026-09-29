@php($video = $video ?? null)
<div class="prose dark:prose-invert max-w-none">
    @if($video)
        <div class="not-prose mb-4">
            @include('filament.components.video-player', ['video' => $video])
        </div>
    @endif
    {!! $content !!}
</div>
