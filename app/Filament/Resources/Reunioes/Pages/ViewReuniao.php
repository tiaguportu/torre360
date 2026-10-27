<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reunioes\Pages;

use App\Enums\ConviteReuniao;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\DocumentosAssinatura\DocumentoAssinaturaResource;
use App\Filament\Resources\Reunioes\ReuniaoResource;
use App\Models\Reuniao;
use App\Models\ReuniaoParticipante;
use App\Services\ReuniaoService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A reunião de ponta a ponta. Cada botão só aparece no passo certo e para quem pode: o super admin passa por qualquer habilidade da
 * política, então a tela também confere a situação, e o serviço recusa o que a regra não permite.
 */
class ViewReuniao extends ViewRecord
{
    use HasAjudaAction;

    protected static string $resource = ReuniaoResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return $this->carregar(parent::resolveRecord($key));
    }

    private function carregar(Model $reuniao): Model
    {
        return $reuniao->load(['organizador', 'turma', 'etapaAvaliativa', 'ataFechadaPor', 'participantes.user', 'adendos.autor']);
    }

    private function recarregar(): void
    {
        $this->record = $this->carregar($this->getRecord()->refresh());
    }

    private function reuniao(): Reuniao
    {
        /** @var Reuniao $reuniao */
        $reuniao = $this->getRecord();

        return $reuniao;
    }

    private function servico(): ReuniaoService
    {
        return app(ReuniaoService::class);
    }

    private function pode(string $habilidade): bool
    {
        return (bool) auth()->user()?->can($habilidade, $this->reuniao());
    }

    private function meuConvite(): ?ReuniaoParticipante
    {
        return $this->reuniao()->participantes()->where('user_id', auth()->id())->first();
    }

    /**
     * Consulta direta: a relação carregada no mount não sobrevive inteira às chamadas seguintes do Livewire.
     *
     * @return Collection<int, ReuniaoParticipante>
     */
    private function participantes(): Collection
    {
        return $this->reuniao()->participantes()->with('user')->get();
    }

    /**
     * @param  callable(): mixed  $acao
     */
    private function executar(callable $acao, string $sucesso, ?string $titulo = null): bool
    {
        try {
            $acao();
        } catch (\DomainException $e) {
            Notification::make()->title($titulo ?? 'Não foi possível concluir')->body($e->getMessage())->danger()->persistent()->send();

            return false;
        } finally {
            $this->recarregar();
        }

        Notification::make()->title($sucesso)->success()->send();

        return true;
    }

    /**
     * @return array<int, string> id do usuário => nome
     */
    private function opcoesDePresenca(): array
    {
        return $this->participantes()->mapWithKeys(fn (ReuniaoParticipante $p): array => [$p->user_id => $p->user?->name ?? "Usuário {$p->user_id}"])->all();
    }

    /**
     * @param  list<int|string>  $marcados
     * @return array<int, bool>
     */
    private function mapaDePresenca(array $marcados): array
    {
        $marcados = array_map('intval', $marcados);

        return $this->participantes()->mapWithKeys(fn (ReuniaoParticipante $p): array => [$p->user_id => in_array($p->user_id, $marcados, true)])->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->editarAction(),
            $this->conviteAction('vou', 'Vou participar', 'heroicon-o-check-circle', 'success', ConviteReuniao::Aceito),
            $this->conviteAction('nao_vou', 'Não posso ir', 'heroicon-o-x-circle', 'danger', ConviteReuniao::Recusado),
            $this->realizarAction(),
            $this->presencaAction(),
            $this->ataAction(),
            $this->fecharAtaAction(),
            $this->adendoAction(),
            $this->pdfAction(),
            $this->assinarAction(),
            $this->verAssinaturaAction(),
            $this->baixarAssinadaAction(),
            $this->cancelarAction(),
            $this->ajudaAction('Reunião', HelpContent::make('👥', 'Acompanhar a reunião', 'Os botões do alto mudam conforme o passo em que a reunião está e o que o seu perfil pode fazer.')
                ->secao('🔁 Passos', [
                    ['✅', 'Responder o convite', 'Quem foi convidado diz se vai participar; a recusa avisa quem organiza.'],
                    ['🟢', 'Marcar como realizada', 'Depois do horário, marque quem esteve presente. A ata e as deliberações só se registram a partir daí.'],
                    ['📝', 'Escrever e fechar a ata', 'O texto fica em rascunho até você fechar. Fechada, a ata não muda mais: correções entram como adendo.'],
                    ['📄', 'PDF e assinatura', 'A ata fechada gera um PDF e pode seguir para a assinatura eletrônica dos presentes.'],
                    ['📌', 'Encaminhamentos', 'Na aba abaixo, registre o que ficou combinado, com responsável e prazo.'],
                ])
                ->dica('No conselho de classe, a aba "Alunos" traz a pauta aluno por aluno; todos precisam ser deliberados para a ata fechar.')),
        ];
    }

    private function editarAction(): Action
    {
        return Action::make('editar')
            ->label('Alterar')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (): bool => $this->reuniao()->estaAgendada() && $this->pode('update'))
            ->url(fn (): string => ReuniaoResource::getUrl('edit', ['record' => $this->reuniao()]));
    }

    private function conviteAction(string $nome, string $rotulo, string $icone, string $cor, ConviteReuniao $resposta): Action
    {
        return Action::make($nome)
            ->label($rotulo)
            ->icon($icone)
            ->color($cor)
            ->visible(fn (): bool => $this->reuniao()->estaAgendada()
                && ($convite = $this->meuConvite()) !== null
                && $convite->papel !== ReuniaoParticipante::PAPEL_ORGANIZADOR
                && $convite->convite !== $resposta)
            ->action(fn () => $this->executar(fn () => $this->servico()->responderConvite($this->reuniao(), auth()->user(), $resposta), 'Resposta registrada'));
    }

    private function realizarAction(): Action
    {
        return Action::make('realizar')
            ->label('Marcar como realizada')
            ->icon('heroicon-o-play-circle')
            ->color('primary')
            ->visible(fn (): bool => $this->reuniao()->estaAgendada() && $this->reuniao()->inicio_em->isPast() && $this->pode('gerenciar'))
            ->modalHeading('Marcar a reunião como realizada')
            ->modalDescription('Marque quem esteve presente. Depois disso a ata pode ser escrita e, no conselho de classe, os alunos deliberados.')
            ->fillForm(fn (): array => ['presentes' => $this->participantes()->where('convite', ConviteReuniao::Aceito)->pluck('user_id')->map(fn ($id): int => (int) $id)->all()])
            ->schema([
                CheckboxList::make('presentes')->label('Presentes')->options(fn (): array => $this->opcoesDePresenca())->columns(2),
            ])
            ->action(function (array $data): void {
                abort_unless($this->pode('gerenciar'), 403);

                $this->executar(fn () => $this->servico()->marcarRealizada($this->reuniao(), auth()->user(), $this->mapaDePresenca((array) ($data['presentes'] ?? []))), 'Reunião registrada como realizada');
            });
    }

    private function presencaAction(): Action
    {
        return Action::make('presenca')
            ->label('Ajustar presença')
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->visible(fn (): bool => $this->reuniao()->foiRealizada() && $this->pode('gerenciar'))
            ->modalHeading('Quem esteve presente')
            ->fillForm(fn (): array => ['presentes' => $this->participantes()->where('presente', true)->pluck('user_id')->map(fn ($id): int => (int) $id)->all()])
            ->schema([
                CheckboxList::make('presentes')->label('Presentes')->options(fn (): array => $this->opcoesDePresenca())->columns(2),
            ])
            ->action(function (array $data): void {
                abort_unless($this->pode('gerenciar'), 403);

                $this->executar(fn () => $this->servico()->registrarPresenca($this->reuniao(), $this->mapaDePresenca((array) ($data['presentes'] ?? []))), 'Presença registrada');
            });
    }

    private function ataAction(): Action
    {
        return Action::make('ata')
            ->label('Escrever ata')
            ->icon('heroicon-o-document-text')
            ->color('primary')
            ->visible(fn (): bool => $this->reuniao()->emAndamento() && $this->pode('redigirAta'))
            ->modalHeading('Ata da reunião')
            ->modalDescription(fn (): string => $this->reuniao()->ehConselho()
                ? 'Registre as considerações gerais do conselho. As deliberações de cada aluno entram na ata automaticamente.'
                : 'O texto fica em rascunho até a ata ser fechada.')
            ->modalWidth('4xl')
            ->fillForm(fn (): array => ['ata' => $this->reuniao()->ata])
            ->schema([
                Textarea::make('ata')->label('Texto da ata')->rows(16)->maxLength(60000),
            ])
            ->action(function (array $data): void {
                abort_unless($this->pode('redigirAta'), 403);

                $this->executar(fn () => $this->servico()->salvarAta($this->reuniao(), $data['ata'] ?? null), 'Ata salva');
            });
    }

    private function fecharAtaAction(): Action
    {
        return Action::make('fechar_ata')
            ->label('Fechar ata')
            ->icon('heroicon-o-lock-closed')
            ->color('success')
            ->visible(fn (): bool => $this->reuniao()->foiRealizada() && $this->pode('redigirAta'))
            ->requiresConfirmation()
            ->modalHeading('Fechar a ata')
            ->modalDescription('Depois de fechada a ata não muda mais: qualquer correção entra como adendo. Os participantes recebem um aviso.')
            ->action(fn () => $this->executar(fn () => $this->servico()->fecharAta($this->reuniao(), auth()->user()), 'Ata fechada', 'A ata não pôde ser fechada'));
    }

    private function adendoAction(): Action
    {
        return Action::make('adendo')
            ->label('Registrar adendo')
            ->icon('heroicon-o-plus-circle')
            ->color('gray')
            ->visible(fn (): bool => $this->reuniao()->ataEstaFechada() && $this->pode('gerenciar'))
            ->modalHeading('Adendo à ata fechada')
            ->modalDescription('O adendo fica registrado com o seu nome e a data. A ata original não é alterada.')
            ->schema([
                Textarea::make('texto')->label('Correção ou complemento')->rows(5)->required()->maxLength(5000),
            ])
            ->action(function (array $data): void {
                abort_unless($this->pode('gerenciar'), 403);

                $this->executar(fn () => $this->servico()->registrarAdendo($this->reuniao(), (string) ($data['texto'] ?? ''), auth()->user()), 'Adendo registrado');
            });
    }

    private function pdfAction(): Action
    {
        return Action::make('pdf')
            ->label(fn (): string => $this->reuniao()->ataEstaFechada() ? 'Baixar PDF da ata' : 'Baixar minuta da ata')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => ($this->reuniao()->foiRealizada() || $this->reuniao()->ataEstaFechada()) && $this->pode('view'))
            ->action(function () {
                $reuniao = $this->reuniao();
                $nome = 'ata-'.Str::slug($reuniao->titulo).'-'.$reuniao->inicio_em->format('Y-m-d').'.pdf';

                return response()->streamDownload(function () use ($reuniao): void {
                    echo $this->servico()->pdfDaAta($reuniao)->output();
                }, $nome, ['Content-Type' => 'application/pdf']);
            });
    }

    private function podeAssinar(): bool
    {
        return $this->servico()->impedimentoDeAssinatura($this->reuniao()) === null
            && $this->pode('gerenciar')
            && (bool) auth()->user()?->can('Create:DocumentoAssinatura');
    }

    private function assinarAction(): Action
    {
        return Action::make('assinar')
            ->label('Enviar para assinatura')
            ->icon('heroicon-o-pencil-square')
            ->color('info')
            ->visible(fn (): bool => $this->podeAssinar())
            ->modalHeading('Enviar a ata para assinatura eletrônica')
            ->modalDescription('O PDF da ata fechada vira um documento para assinatura no Assinafy. Você confere a lista, e na tela do documento envia o e-mail aos assinantes.')
            ->modalSubmitActionLabel('Montar o documento')
            ->fillForm(fn (): array => ['assinantes' => $this->servico()->signatariosSugeridos($this->reuniao())['assinantes']])
            ->schema([
                Repeater::make('assinantes')
                    ->label('Quem assina')
                    ->schema([
                        TextInput::make('nome')->label('Nome')->required()->maxLength(160),
                        TextInput::make('email')->label('E-mail')->email()->required()->maxLength(160),
                        TextInput::make('papel')->label('Papel')->maxLength(40),
                    ])
                    ->columns(3)
                    ->minItems(1)
                    ->maxItems(20)
                    ->addActionLabel('Adicionar assinante')
                    ->helperText('Sugestão: quem esteve presente e tem e-mail cadastrado. Ajuste se a assinatura for de outras pessoas.'),
            ])
            ->action(function (array $data) {
                abort_unless($this->podeAssinar(), 403);

                try {
                    $documento = $this->servico()->enviarAtaParaAssinatura($this->reuniao(), $data['assinantes'] ?? [], auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Não foi possível montar o documento')->body($e->getMessage())->danger()->persistent()->send();

                    return null;
                }

                return redirect(DocumentoAssinaturaResource::getUrl('view', ['record' => $documento]));
            });
    }

    private function verAssinaturaAction(): Action
    {
        return Action::make('ver_assinatura')
            ->label('Ver assinatura')
            ->icon('heroicon-o-document-magnifying-glass')
            ->color('gray')
            ->visible(fn (): bool => ! $this->reuniao()->ehSigilosa()
                && (bool) auth()->user()?->can('ViewAny:DocumentoAssinatura')
                && $this->reuniao()->documentosAssinatura()->exists())
            ->url(fn (): string => DocumentoAssinaturaResource::getUrl('view', ['record' => $this->reuniao()->documentosAssinatura()->first()]));
    }

    private function baixarAssinadaAction(): Action
    {
        return Action::make('baixar_assinada')
            ->label('Baixar cópia assinada')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (): bool => $this->servico()->caminhoDaCopiaAssinada($this->reuniao()) !== null)
            ->url(fn (): string => route('documentos.visualizar', ['path' => $this->reuniao()->arquivo_assinado_path]))
            ->openUrlInNewTab();
    }

    private function cancelarAction(): Action
    {
        return Action::make('cancelar')
            ->label('Cancelar reunião')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (): bool => $this->reuniao()->estaAgendada() && $this->pode('gerenciar'))
            ->modalHeading('Cancelar a reunião')
            ->modalDescription('O espaço reservado é liberado e os participantes recebem um aviso com o motivo.')
            ->schema([
                Textarea::make('motivo')->label('Motivo')->required()->rows(2)->maxLength(500),
            ])
            ->action(function (array $data): void {
                abort_unless($this->pode('gerenciar'), 403);

                $this->executar(fn () => $this->servico()->cancelar($this->reuniao(), auth()->user(), (string) ($data['motivo'] ?? '')), 'Reunião cancelada');
            });
    }
}
