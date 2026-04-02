@php
    $state = $getState();
    $percent = $state['percent'];
    $label = $state['label'];
    
    $colorClass = match(true) {
        $percent < 30 => 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.4)]',
        $percent < 70 => 'bg-amber-500 shadow-[0_0_10px_rgba(245,158,11,0.4)]',
        $percent < 100 => 'bg-blue-500 shadow-[0_0_10px_rgba(59,130,246,0.4)]',
        default => 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]',
    };

    $labelColorClass = match(true) {
        $percent < 30 => 'text-red-600 dark:text-red-400',
        $percent < 70 => 'text-amber-600 dark:text-amber-400',
        $percent < 100 => 'text-blue-600 dark:text-blue-400',
        default => 'text-emerald-600 dark:text-emerald-400',
    };
@endphp

<div class="flex flex-col gap-1.5 min-w-[140px] px-2 py-1 group transition-all duration-300">
    <div class="flex justify-between items-end text-[10px] font-bold uppercase tracking-tight">
        <span class="flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
            @if($percent == 100)
                <x-heroicon-m-check-badge class="w-3.5 h-3.5 text-emerald-500 animate-pulse" />
            @else
                <x-heroicon-m-arrow-trending-up class="w-3 h-3 text-gray-400" />
            @endif
            <span class="font-semibold">{{ $label }}</span>
        </span>
        <span class="{{ $labelColorClass }} font-black text-xs">{{ $percent }}%</span>
    </div>
    
    <div class="relative w-full h-2.5 bg-gray-100 dark:bg-gray-800/50 rounded-full overflow-hidden border border-gray-200/50 dark:border-gray-700/50 shadow-inner">
        {{-- Shine Layer --}}
        @if($percent > 0 && $percent < 100)
            <div class="absolute inset-0 z-10 w-full animate-shimmer pointer-events-none opacity-40 mix-blend-overlay">
                 <div class="w-1/2 h-full bg-linear-to-r from-transparent via-white to-transparent transform -skew-x-12"></div>
            </div>
        @endif
        
        {{-- Progress Fill --}}
        <div 
            class="h-full rounded-full transition-all duration-1000 ease-out relative z-0 {{ $colorClass }}" 
            style="width: {{ $percent }}%"
        >
            {{-- Bubbles/Glass Effect for emerald/100% --}}
            @if($percent == 100)
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.4)_0%,transparent_80%)] opacity-50"></div>
                <div class="absolute top-0 left-0 w-full h-[1px] bg-white/40"></div>
            @else
                <div class="absolute top-0 left-0 w-full h-[1px] bg-white/20"></div>
            @endif
        </div>
    </div>
</div>

<style>
    @keyframes shimmer {
        0% { transform: translateX(-150%); }
        100% { transform: translateX(150%); }
    }
    .animate-shimmer {
        animation: shimmer 3s infinite ease-in-out;
    }
</style>

