<?php

namespace App\Filament\Portal\Pages;

use App\Enums\StatusChamado;
use App\Models\AtendimentoChamado;
use App\Models\AtendimentoMensagem;
use App\Models\AtendimentoSetor;
use App\Models\Matricula;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\WithFileUploads;
use UnitEnum;

class CentralAtendimento extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Central de Atendimento';

    protected static ?string $slug = 'atendimento';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.portal.pages.central-atendimento';

    // Modal de Novo Chamado
    public bool $showNovoModal = false;

    public ?int $novoMatriculaId = null;

    public ?int $novoSetorId = null;

    public string $novoAssunto = '';

    public string $novaPrioridade = 'normal';

    public string $novaMensagemInicial = '';

    public $novoAnexo = null;

    // Modal de Conversa
    public bool $showConversaModal = false;

    public ?int $chamadoSelecionadoId = null;

    public string $respostaTexto = '';

    public $respostaAnexo = null;

    // Avaliação do Chamado
    public ?int $notaAvaliacao = null;

    public string $comentarioAvaliacao = '';

    public function getSetoresProperty(): Collection
    {
        return AtendimentoSetor::where('ativo', true)->orderBy('ordem')->get();
    }

    public function getMatriculasAcessiveisProperty(): Collection
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Matricula::query()
            ->whereIn('pessoa_id', $idsAcessiveis)
            ->with(['pessoa', 'turma.serie'])
            ->get();
    }

    public function getMeusChamadosProperty(): Collection
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return AtendimentoChamado::query()
            ->where(function ($q) use ($idsAcessiveis) {
                $q->whereIn('solicitante_id', $idsAcessiveis)
                    ->orWhereHas('matricula', fn ($sub) => $sub->whereIn('pessoa_id', $idsAcessiveis));
            })
            ->with(['setor', 'matricula.pessoa', 'mensagens.user', 'mensagens.pessoa'])
            ->latest('id')
            ->get();
    }

    public function getChamadoAtualProperty(): ?AtendimentoChamado
    {
        if (! $this->chamadoSelecionadoId) {
            return null;
        }

        return AtendimentoChamado::with(['setor', 'matricula.pessoa', 'mensagens.user', 'mensagens.pessoa'])
            ->find($this->chamadoSelecionadoId);
    }

    public function abrirNovoChamadoModal(): void
    {
        $this->reset(['novoMatriculaId', 'novoSetorId', 'novoAssunto', 'novaMensagemInicial', 'novoAnexo']);
        $this->novaPrioridade = 'normal';
        $this->showNovoModal = true;
    }

    public function criarChamado(): void
    {
        $this->validate([
            'novoSetorId' => 'required|exists:atendimento_setores,id',
            'novoAssunto' => 'required|string|min:4|max:255',
            'novaMensagemInicial' => 'required|string|min:5',
        ], [
            'novoSetorId.required' => 'Por favor, selecione o setor de destino.',
            'novoAssunto.required' => 'O assunto é obrigatório.',
            'novaMensagemInicial.required' => 'A mensagem explicativa é obrigatória.',
        ]);

        $anexoPath = null;
        if ($this->novoAnexo) {
            $anexoPath = $this->novoAnexo->store('atendimentos/anexos', 'public');
        }

        $solicitanteId = auth()->user()->pessoa?->id ?: auth()->user()->pessoasAcessiveis()->first()?->id;

        $chamado = AtendimentoChamado::create([
            'setor_id' => $this->novoSetorId,
            'matricula_id' => $this->novoMatriculaId ?: null,
            'solicitante_id' => $solicitanteId,
            'assunto' => $this->novoAssunto,
            'prioridade' => $this->novaPrioridade,
            'status' => StatusChamado::Aberto,
        ]);

        AtendimentoMensagem::create([
            'chamado_id' => $chamado->id,
            'pessoa_id' => $solicitanteId,
            'mensagem' => $this->novaMensagemInicial,
            'anexo_path' => $anexoPath,
        ]);

        $this->showNovoModal = false;

        Notification::make()
            ->title('Chamado Aberto com Sucesso!')
            ->body("Protocolo gerado: {$chamado->protocolo}. Nossa equipe responderá em breve.")
            ->success()
            ->send();
    }

    public function verConversa(int $chamadoId): void
    {
        $chamado = $this->meusChamados->firstWhere('id', $chamadoId);

        if (! $chamado) {
            Notification::make()->title('Chamado não localizado.')->danger()->send();

            return;
        }

        $this->chamadoSelecionadoId = $chamadoId;
        $this->respostaTexto = '';
        $this->respostaAnexo = null;
        $this->notaAvaliacao = $chamado->avaliacao_nota;
        $this->comentarioAvaliacao = $chamado->avaliacao_comentario ?? '';
        $this->showConversaModal = true;
    }

    public function enviarResposta(): void
    {
        $this->validate([
            'respostaTexto' => 'required|string|min:2',
        ], [
            'respostaTexto.required' => 'Digite sua mensagem antes de enviar.',
        ]);

        $chamado = $this->chamadoAtual;
        if (! $chamado) {
            return;
        }

        $anexoPath = null;
        if ($this->respostaAnexo) {
            $anexoPath = $this->respostaAnexo->store('atendimentos/anexos', 'public');
        }

        $solicitanteId = auth()->user()->pessoa?->id ?: auth()->user()->pessoasAcessiveis()->first()?->id;

        AtendimentoMensagem::create([
            'chamado_id' => $chamado->id,
            'pessoa_id' => $solicitanteId,
            'mensagem' => $this->respostaTexto,
            'anexo_path' => $anexoPath,
        ]);

        // Se o chamado estava aguardando o solicitante ou resolvido, reabre para em andamento
        if (in_array($chamado->status, [StatusChamado::AguardandoSolicitante, StatusChamado::Resolvido])) {
            $chamado->update(['status' => StatusChamado::EmAndamento]);
        }

        $this->reset(['respostaTexto', 'respostaAnexo']);

        Notification::make()
            ->title('Mensagem enviada!')
            ->body('Sua mensagem foi adicionada ao histórico do chamado.')
            ->success()
            ->send();
    }

    public function enviarAvaliacao(): void
    {
        if (! $this->notaAvaliacao) {
            Notification::make()->title('Escolha uma nota de 1 a 5 estrelas.')->warning()->send();

            return;
        }

        $chamado = $this->chamadoAtual;
        if (! $chamado) {
            return;
        }

        $chamado->update([
            'avaliacao_nota' => $this->notaAvaliacao,
            'avaliacao_comentario' => $this->comentarioAvaliacao,
            'status' => StatusChamado::Fechado,
            'fechado_em' => now(),
        ]);

        Notification::make()
            ->title('Obrigado pela sua avaliação!')
            ->body('Seu feedback é fundamental para o aprimoramento contínuo do nosso atendimento.')
            ->success()
            ->send();
    }
}
