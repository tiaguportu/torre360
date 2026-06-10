@if(auth()->check() && (auth()->user()->can('use_assistant') || auth()->user()->can('UseAssistant')))
    @livewire('assistant-chat-bubble')
@endif
@vite('resources/js/app.js')
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js');
        });
    }
</script>
