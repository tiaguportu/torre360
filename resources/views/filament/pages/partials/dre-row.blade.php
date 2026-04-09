<tr class="{{ $level === 1 ? 'bg-gray-50/50 dark:bg-white/5 font-semibold' : '' }}">
    <td class="px-6 py-2 text-sm text-gray-500 whitespace-nowrap dark:text-gray-400">
        {{ $item['codigo'] }}
    </td>
    <td class="px-6 py-2 text-sm whitespace-nowrap" style="padding-left: {{ 1.5 + ($level * 1) }}rem">
        {{ $item['nome'] }}
    </td>
    <td class="px-6 py-2 text-right text-sm font-medium whitespace-nowrap {{ $item['total'] < 0 ? 'text-danger-600' : '' }}">
        {{ number_format($item['total'], 2, ',', '.') }}
    </td>
</tr>

@if(isset($item['filhos']) && count($item['filhos']) > 0)
    @foreach($item['filhos'] as $filho)
        @include('filament.pages.partials.dre-row', ['item' => $filho, 'level' => $level + 1])
    @endforeach
@endif
