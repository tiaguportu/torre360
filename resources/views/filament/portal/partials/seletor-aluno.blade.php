@php
    $opcoes = $this->getOpcoesAlunos();
    $selecionada = $this->getMatriculaSelecionada();
@endphp

@if (count($opcoes) > 1)
    <div class="pf-seletor">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="matriculaId" aria-label="Aluno">
                @foreach ($opcoes as $id => $rotulo)
                    <option value="{{ $id }}" @selected($selecionada?->id === $id)>{{ $rotulo }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
@elseif (count($opcoes) === 1)
    <p class="pf-muted">{{ collect($opcoes)->first() }}</p>
@endif
