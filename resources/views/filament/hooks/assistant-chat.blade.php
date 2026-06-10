@php
    if (auth()->check()) {
        $user = auth()->user();
        $canUse = $user->can('use_assistant') || $user->can('UseAssistant');
        \Illuminate\Support\Facades\Log::info("Assistant hook evaluated", [
            'user_id' => $user->id,
            'email' => $user->email,
            'active_role' => session('active_role'),
            'can_use_assistant' => $user->can('use_assistant'),
            'can_UseAssistant' => $user->can('UseAssistant'),
            'result' => $canUse,
        ]);
    } else {
        \Illuminate\Support\Facades\Log::info("Assistant hook evaluated: Guest user");
    }
@endphp

<!-- ASSISTANT HOOK DEBUG:
    auth_check: {{ auth()->check() ? 'true' : 'false' }}
    @if(auth()->check())
    user_id: {{ auth()->user()->id }}
    active_role: {{ session('active_role') }}
    can_use_assistant: {{ auth()->user()->can('use_assistant') ? 'true' : 'false' }}
    can_UseAssistant: {{ auth()->user()->can('UseAssistant') ? 'true' : 'false' }}
    @endif
-->

@if(auth()->check() && (auth()->user()->can('use_assistant') || auth()->user()->can('UseAssistant')))
    @livewire('assistant-chat-bubble')
@endif
@vite(['resources/css/app.css', 'resources/js/app.js'])
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js');
        });
    }
</script>
