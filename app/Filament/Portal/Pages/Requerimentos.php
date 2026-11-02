<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\AtendimentoChamado;
use App\Models\Matricula;
use App\Services\AtendimentoService;
use App\Support\HelpContent;
use App\Support\Requerimentos\CatalogoRequerimentos;
use App\Support\Requerimentos\TipoRequerimento;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;
use UnitEnum;

/**
 * Requerimentos da família (Onda 80) e pedidos do titular de dados (Onda 34): o catálogo de pedidos com formulário próprio.
 * O pedido vira um chamado com tipo, prazo e protocolo, acompanhado em "Meus pedidos" e na Central de Atendimento.
 */
class Requerimentos extends Page
{
    use HasAjudaAction;
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Requerimentos';

    protected static ?string $slug = 'requerimentos';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.portal.pages.requerimentos';

    public ?string $tipoSelecionado = null;

    public ?int $matriculaId = null;

    /** @var array<string, mixed> */
    public array $campos = [];

    public $anexo = null;

    /**
     * @return array<string, TipoRequerimento>
     */
    public function getTiposProperty(): array
    {
        return CatalogoRequerimentos::todos();
    }

    public function getTipoAtualProperty(): ?TipoRequerimento
    {
        return $this->tipoSelecionado ? CatalogoRequerimentos::tipo($this->tipoSelecionado) : null;
    }

    public function getMatriculasAcessiveisProperty(): Collection
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Matricula::query()->whereIn('pessoa_id', $idsAcessiveis)->with(['pessoa', 'turma'])->get();
    }

    public function getMeusPedidosProperty(): Collection
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return AtendimentoChamado::query()
            ->whereNotNull('tipo_requerimento')
            ->where(fn ($q) => $q->whereIn('solicitante_id', $idsAcessiveis)
                ->orWhereHas('matricula', fn ($sub) => $sub->whereIn('pessoa_id', $idsAcessiveis)))
            ->with(['matricula.pessoa'])
            ->latest('id')
            ->limit(30)
            ->get();
    }

    public function escolher(string $chave): void
    {
        if (! CatalogoRequerimentos::tipo($chave)) {
            return;
        }

        $this->tipoSelecionado = $chave;
        $this->matriculaId = null;
        $this->campos = [];
        $this->anexo = null;
        $this->resetErrorBag();
    }

    public function voltar(): void
    {
        $this->reset(['tipoSelecionado', 'matriculaId', 'campos', 'anexo']);
        $this->resetErrorBag();
    }

    public function enviar(): void
    {
        $tipo = $this->tipoAtual;

        if (! $tipo) {
            return;
        }

        $matriculasIds = $this->matriculasAcessiveis->pluck('id')->all();

        if ($this->matriculaId && ! in_array($this->matriculaId, $matriculasIds, true)) {
            $this->addError('matriculaId', 'O aluno escolhido não é acessível pelo seu usuário.');

            return;
        }

        $campos = $this->campos;

        foreach ($tipo->camposDeArquivo() as $campoArquivo) {
            if ($this->anexo) {
                $this->validate(['anexo' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:5120'], [
                    'anexo.mimes' => 'O anexo deve ser PDF ou imagem (JPG, PNG, WEBP).',
                    'anexo.max' => 'O anexo não pode ser maior que 5MB.',
                ]);
                $campos[$campoArquivo['nome']] = $this->anexo->store('atendimentos/requerimentos', 'local');
            } elseif ($campoArquivo['obrigatorio'] ?? false) {
                $this->addError('anexo', 'Anexe o arquivo pedido.');

                return;
            }
        }

        $solicitanteId = auth()->user()->pessoa?->id ?: auth()->user()->pessoasAcessiveis()->first()?->id;

        try {
            $chamado = app(AtendimentoService::class)->abrirRequerimento($tipo->chave, (int) $solicitanteId, $this->matriculaId, $campos);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensagens) {
                $this->addError($campo === 'matricula_id' ? 'matriculaId' : "campos.{$campo}", $mensagens[0]);
            }

            return;
        }

        $this->voltar();

        Notification::make()
            ->title('Pedido enviado!')
            ->body("Protocolo {$chamado->protocolo}. Você acompanha o andamento em \"Meus pedidos\" e recebe um aviso quando houver resposta.")
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Requerimentos', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('📝', 'Requerimentos', 'Peça à escola o que precisa, com protocolo e prazo.')
            ->passos('🚀 Como fazer um pedido', [
                'Escolha o tipo de pedido.',
                'Preencha o formulário e anexe o que for pedido.',
                'Acompanhe em "Meus pedidos". Você recebe um aviso quando a escola responder.',
            ])
            ->dica('Para segunda via de declaração ou de histórico, use "Solicitações de documentos".');
    }
}
