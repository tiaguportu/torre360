<x-filament-panels::page>
    @include('filament.portal.partials.estilos')

    <div class="pf-stack">
        @include('filament.portal.partials.seletor-aluno')

        @if (! $this->getMatriculaSelecionada())
            <div class="pf-vazio">Nenhum aluno vinculado ao seu cadastro foi encontrado. Entre em contato com a secretaria caso isso não esteja correto.</div>
        @else
            <div>
                {{ $this->table }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
