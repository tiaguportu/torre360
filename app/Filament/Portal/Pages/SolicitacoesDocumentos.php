<?php

namespace App\Filament\Portal\Pages;

use App\Enums\StatusSolicitacaoDocumento;
use App\Models\Matricula;
use App\Models\SolicitacaoDocumento;
use App\Models\TemplateDocumento;
use App\Services\DocumentoService;
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
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class SolicitacoesDocumentos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Solicitar Documentos Oficiais';

    protected static ?string $slug = 'solicitacoes-documentos';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.portal.pages.solicitacoes-documentos';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getSolicitacoesQuery())
            ->headerActions([
                Action::make('solicitar')
                    ->label('Nova Solicitação de Documento')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->modalHeading('Requerer Documento ou Declaração Escolar')
                    ->modalDescription('Escolha o estudante e o tipo de certidão que necessita. O documento será autenticado digitalmente por QR Code.')
                    ->form([
                        Select::make('matricula_id')
                            ->label('Estudante')
                            ->options(fn () => $this->getMatriculasOptions())
                            ->required(),

                        Select::make('template_documento_id')
                            ->label('Tipo de Documento Desejado')
                            ->options(TemplateDocumento::where('is_ativo', true)->pluck('nome', 'id'))
                            ->required(),

                        Textarea::make('observacao_solicitante')
                            ->label('Finalidade / Observações')
                            ->placeholder('Ex: Para fins de comprovação em plano de saúde, estágio ou clube.')
                            ->rows(2),
                    ])
                    ->action(function (array $data, DocumentoService $service) {
                        try {
                            $template = TemplateDocumento::findOrFail($data['template_documento_id']);

                            $solicitacao = SolicitacaoDocumento::create([
                                'protocolo' => SolicitacaoDocumento::gerarProtocolo(),
                                'codigo_verificacao' => SolicitacaoDocumento::gerarCodigoVerificacao(),
                                'matricula_id' => $data['matricula_id'],
                                'template_documento_id' => $data['template_documento_id'],
                                'solicitado_por_user_id' => auth()->id(),
                                'status' => StatusSolicitacaoDocumento::Disponivel,
                                'observacao_solicitante' => $data['observacao_solicitante'] ?? null,
                                'data_solicitacao' => now(),
                                'data_emissao' => now(),
                                'data_validade' => now()->addDays($template->validade_dias ?? 30),
                            ]);

                            // Gera imediatamente o PDF oficial com o QR Code
                            $service->gerarPdf($solicitacao);

                            Notification::make()
                                ->title('Documento Emitido com Sucesso!')
                                ->body("O documento com Protocolo {$solicitacao->protocolo} já está pronto para download com validação digital por QR Code.")
                                ->success()
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
                    ->searchable(),

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
                    ->visible(fn (SolicitacaoDocumento $record) => $record->status === StatusSolicitacaoDocumento::Disponivel && ! empty($record->arquivo_path) && Storage::disk('public')->exists($record->arquivo_path))
                    ->url(fn (SolicitacaoDocumento $record) => Storage::disk('public')->url($record->arquivo_path))
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
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Matricula::query()
            ->whereIn('pessoa_id', $idsAcessiveis)
            ->with(['pessoa', 'turma.serie'])
            ->get()
            ->mapWithKeys(function (Matricula $m) {
                $nomeAluno = $m->pessoa?->nome ?? 'Estudante';
                $turma = $m->turma?->nome ? " — Turma: {$m->turma->nome}" : '';

                return [$m->id => "{$nomeAluno}{$turma}"];
            })
            ->toArray();
    }
}
