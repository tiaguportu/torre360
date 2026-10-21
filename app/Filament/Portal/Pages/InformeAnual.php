<?php

declare(strict_types=1);

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Services\InformeAnualMensalidadesService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Portal da Família: informe anual de mensalidades pagas, por aluno, para a declaração do imposto de renda. Só os alunos
 * da própria família (ou em que a pessoa é responsável financeira).
 */
class InformeAnual extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Informe Anual';

    protected static ?string $navigationLabel = 'Informe Anual';

    protected static ?string $slug = 'informe-anual';

    protected string $view = 'filament.portal.pages.informe-anual';

    public int $ano;

    public function mount(): void
    {
        $this->ano = (int) today()->subYear()->format('Y');
    }

    /**
     * @return array<int, string>
     */
    public function getAnos(): array
    {
        $atual = (int) today()->format('Y');

        return collect(range($atual, $atual - 5))->mapWithKeys(fn (int $a): array => [$a => (string) $a])->all();
    }

    public function anoValido(): int
    {
        return array_key_exists($this->ano, $this->getAnos()) ? $this->ano : (int) today()->subYear()->format('Y');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getInformes(): Collection
    {
        $servico = app(InformeAnualMensalidadesService::class);
        $ids = $servico->matriculasDaFamilia(auth()->user())->pluck('id');

        return $ids->isEmpty() ? collect() : $servico->gerar($this->anoValido(), $ids);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Informe Anual', HelpContent::make('🧾', 'Informe Anual', 'O que você pagou à escola no ano, para a declaração do imposto de renda.')
                ->secao('📖 Como usar', [
                    ['📅', 'Escolha o ano', 'Normalmente é o ano anterior ao da declaração.'],
                    ['📄', 'Baixe o PDF', 'Um por aluno, com os pagamentos mês a mês e a parte estimada de despesa com instrução.'],
                ])
                ->alerta('O informe considera os pagamentos que a escola recebeu. Material, uniforme, alimentação e transporte não entram como instrução. Se algo não bater, fale com a secretaria.')),
        ];
    }
}
