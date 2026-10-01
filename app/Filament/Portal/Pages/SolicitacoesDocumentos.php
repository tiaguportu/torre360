<?php

namespace App\Filament\Portal\Pages;

use App\Enums\StatusSolicitacaoDocumento;
use App\Filament\Concerns\HasAjudaAction;
use App\Models\HistoricoEscolar;
use App\Models\Matricula;
use App\Models\SolicitacaoDocumento;
use App\Models\TemplateDocumento;
use App\Services\DocumentoService;
use App\Support\HelpContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class SolicitacoesDocumentos extends Page implements HasTable
{
    use HasAjudaAction;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $navigationLabel = 'Declarações e Documentos';

    protected static ?string $title = 'Declarações e Documentos Oficiais';

    protected static ?string $slug = 'solicitacoes-documentos';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.portal.pages.solicitacoes-documentos';

    public ?int $alunoSelecionadoId = null;

    public function mount(): void
    {
        $alunoQuery = request()->query('aluno');
        $matriculasAcessiveis = $this->matriculasAcessiveis;

        if ($alunoQuery && $matriculasAcessiveis->contains('id', (int) $alunoQuery)) {
            $this->alunoSelecionadoId = (int) $alunoQuery;
        } else {
            $this->alunoSelecionadoId = $matriculasAcessiveis->first()?->id;
        }
    }

    public function selecionarAluno(int $matriculaId): void
    {
        if ($this->matriculasAcessiveis->contains('id', $matriculaId)) {
            $this->alunoSelecionadoId = $matriculaId;
        }
    }

    public function getMatriculasAcessiveisProperty(): Collection
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Matricula::query()
            ->whereIn('pessoa_id', $idsAcessiveis)
            ->with(['pessoa', 'turma.serie.curso', 'turma.turno', 'periodoLetivo'])
            ->get();
    }

    public function getMatriculaAtualProperty(): ?Matricula
    {
        if (! $this->alunoSelecionadoId) {
            return $this->matriculasAcessiveis->first();
        }

        return $this->matriculasAcessiveis->firstWhere('id', $this->alunoSelecionadoId);
    }

    public function getTemplatesDisponiveisProperty(): Collection
    {
        return TemplateDocumento::query()
            ->where('is_ativo', true)
            ->orderBy('nome')
            ->get();
    }

    public function getHistoricoEscolarProperty(): ?HistoricoEscolar
    {
        $matricula = $this->matriculaAtual;
        if (! $matricula || ! $matricula->pessoa_id) {
            return null;
        }

        return HistoricoEscolar::query()
            ->where('pessoa_id', $matricula->pessoa_id)
            ->latest('id')
            ->first();
    }

    /**
     * Emissão instantânea com 1 clique a partir dos cards de auto-atendimento.
     */
    public function emitirInstantaneo(int $templateId, DocumentoService $service): void
    {
        $matricula = $this->matriculaAtual;

        if (! $matricula) {
            Notification::make()
                ->title('Nenhum estudante selecionado')
                ->body('Selecione um estudante antes de requerer a declaração.')
                ->warning()
                ->send();

            return;
        }

        $template = TemplateDocumento::find($templateId);

        if (! $template || ! $template->is_ativo) {
            Notification::make()
                ->title('Modelo de documento não disponível')
                ->danger()
                ->send();

            return;
        }

        try {
            $solicitacao = $service->emitirDocumento($matricula, $template, null, auth()->user());

            $downloadUrl = route('documentos.visualizar', ['path' => $solicitacao->arquivo_path]);

            Notification::make()
                ->title('Declaração Emitida com Sucesso!')
                ->body("A {$template->nome} (Protocolo: {$solicitacao->protocolo}) foi gerada com carimbo digital e QR Code.")
                ->success()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('baixar')
                        ->label('Abrir PDF')
                        ->url($downloadUrl, shouldOpenInNewTab: true)
                        ->button(),
                ])
                ->send();
        } catch (\DomainException $e) {
            Notification::make()
                ->title('Atenção ao emitir declaração')
                ->body($e->getMessage())
                ->warning()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erro ao processar documento')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getSolicitacoesQuery())
            ->headerActions([
                Action::make('solicitar_personalizado')
                    ->label('Outra Solicitação / Com Observação')
                    ->icon('heroicon-o-plus-circle')
                    ->color('gray')
                    ->modalHeading('Requerer Documento ou Declaração com Finalidade')
                    ->modalDescription('Escolha o estudante e o tipo de certidão que necessita, informando eventuais observações.')
                    ->form([
                        Select::make('matricula_id')
                            ->label('Estudante')
                            ->options(fn () => $this->getMatriculasOptions())
                            ->default(fn () => $this->alunoSelecionadoId)
                            ->required(),

                        Select::make('template_documento_id')
                            ->label('Tipo de Documento Desejado')
                            ->options(TemplateDocumento::where('is_ativo', true)->pluck('nome', 'id'))
                            ->required(),

                        Textarea::make('observacao_solicitante')
                            ->label('Finalidade / Observações')
                            ->placeholder('Ex: Para fins de comprovação em plano de saúde, estágio supervisionado ou clube.')
                            ->rows(3),
                    ])
                    ->action(function (array $data, DocumentoService $service) {
                        try {
                            $matricula = Matricula::findOrFail($data['matricula_id']);
                            $template = TemplateDocumento::findOrFail($data['template_documento_id']);

                            $solicitacao = $service->emitirDocumento(
                                $matricula,
                                $template,
                                $data['observacao_solicitante'] ?? null,
                                auth()->user()
                            );

                            $downloadUrl = route('documentos.visualizar', ['path' => $solicitacao->arquivo_path]);

                            Notification::make()
                                ->title('Documento Emitido com Sucesso!')
                                ->body("Protocolo {$solicitacao->protocolo} disponível para download imediato.")
                                ->success()
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('baixar')
                                        ->label('Abrir PDF')
                                        ->url($downloadUrl, shouldOpenInNewTab: true)
                                        ->button(),
                                ])
                                ->send();
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->title('Atenção ao emitir declaração')
                                ->body($e->getMessage())
                                ->warning()
                                ->persistent()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Erro ao processar documento')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->columns([
                TextColumn::make('protocolo')
                    ->label('Protocolo')
                    ->weight('bold')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Protocolo copiado'),

                TextColumn::make('matricula.pessoa.nome')
                    ->label('Estudante')
                    ->searchable(),

                TextColumn::make('templateDocumento.nome')
                    ->label('Documento')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),

                TextColumn::make('data_emissao')
                    ->label('Data de Emissão')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Em processamento'),

                TextColumn::make('data_validade')
                    ->label('Válido até')
                    ->date('d/m/Y')
                    ->placeholder('-'),
            ])
            ->recordActions([
                Action::make('baixar')
                    ->label('Baixar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (SolicitacaoDocumento $record) => $record->status === StatusSolicitacaoDocumento::Disponivel && ! empty($record->arquivo_path) && (Storage::disk('local')->exists($record->arquivo_path) || Storage::disk('public')->exists($record->arquivo_path)))
                    ->url(fn (SolicitacaoDocumento $record) => route('documentos.visualizar', ['path' => $record->arquivo_path]))
                    ->openUrlInNewTab(),

                Action::make('conferir_qr')
                    ->label('Verificar QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->url(fn (SolicitacaoDocumento $record) => route('documentos.validar-autenticidade', $record->codigo_verificacao))
                    ->openUrlInNewTab(),
            ])
            ->stackedOnMobile();
    }

    protected function getSolicitacoesQuery(): Builder
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return SolicitacaoDocumento::query()
            ->whereHas('matricula', fn (Builder $q) => $q->whereIn('pessoa_id', $idsAcessiveis))
            ->with(['matricula.pessoa', 'templateDocumento'])
            ->latest('id');
    }

    protected function getMatriculasOptions(): array
    {
        return $this->matriculasAcessiveis
            ->mapWithKeys(function (Matricula $m) {
                $nomeAluno = $m->pessoa?->nome ?? 'Estudante';
                $turma = $m->turma?->nome ? " — Turma: {$m->turma->nome}" : '';

                return [$m->id => "{$nomeAluno}{$turma}"];
            })
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Auto-atendimento de Declarações', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🏛️', 'Auto-atendimento de Declarações e Documentos', 'Emita declarações oficiais da escola instantaneamente sem filas na secretaria.')
            ->passos('🚀 Como emitir declarações rápidas', [
                'Selecione o estudante no seletor do topo.',
                'Clique em "Emitir Agora" no cartão da declaração desejada (Matrícula, Frequência, Horário, Transporte, etc.).',
                'O documento em PDF oficial com carimbo digital e QR Code é gerado na hora para download imediato.',
            ])
            ->secao('📋 Segurança e Validação Jurídica', [
                ['🛡️', 'Validação por QR Code', 'Cada documento possui código único de autenticidade rastreável.'],
                ['💳', 'Declaração de Quitação', 'Exige adimplência financeira do contrato da matrícula no sistema.'],
                ['📜', 'Histórico Escolar', 'Acesse o espelho ou histórico oficial multi-ano do aluno.'],
                ['📊', 'Boletim Escolar', 'Baixe o boletim escolar atualizado com notas e faltas.'],
            ])
            ->dica('Apresente o PDF ou impresso: terceiros podem conferir a autenticidade apontando a câmera do celular para o QR Code.');
    }
}
