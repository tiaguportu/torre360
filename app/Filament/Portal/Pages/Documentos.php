<?php

namespace App\Filament\Portal\Pages;

use App\Models\Contrato;
use App\Services\AssinafyService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class Documentos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static UnitEnum|string|null $navigationGroup = 'Meus Dados';

    protected static ?string $title = 'Documentos e Contratos';

    protected static ?string $slug = 'documentos';

    protected string $view = 'filament.portal.pages.documentos';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getContratosQuery())
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable(),
                TextColumn::make('data_aceite')
                    ->label('Data de Aceite')
                    ->date('d/m/Y')
                    ->placeholder('Ainda não'),
                TextColumn::make('assinafy_status')
                    ->label('Assinatura')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'signed', 'completed' => 'success',
                        'enviado', 'pending' => 'warning',
                        'erro_envio', 'rejected', 'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'signed', 'completed' => 'Assinado',
                        'enviado', 'pending' => 'Pendente',
                        'erro_envio' => 'Erro no Envio',
                        'rejected' => 'Recusado',
                        'canceled' => 'Cancelado',
                        default => 'Não enviado',
                    }),
            ])
            ->recordActions([
                Action::make('visualizar')
                    ->label('Ver Contrato')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Contrato $record) => route('contratos.visualizar', $record))
                    ->openUrlInNewTab(),
                Action::make('baixar_pdf')
                    ->label('Baixar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Contrato $record) => route('contratos.pdf', $record))
                    ->openUrlInNewTab(),
                Action::make('baixar_assinado')
                    ->label('Baixar Assinado')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->url(fn (Contrato $record) => route('contratos.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Contrato $record) => in_array($record->assinafy_status, ['signed', 'completed'])),
                Action::make('assinar')
                    ->label('Assinar Contrato')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (Contrato $record) => ! in_array($record->assinafy_status, ['signed', 'completed']))
                    ->action(function (Contrato $record, AssinafyService $service) {
                        $result = $service->enviarContrato($record);

                        if ($result['success'] && isset($result['redirect_url'])) {
                            return redirect()->away($result['redirect_url']);
                        }

                        Notification::make()
                            ->title('Erro ao processar assinatura')
                            ->body($result['message'] ?? 'Não foi possível obter a URL de assinatura.')
                            ->danger()
                            ->send();
                    }),
            ])
            ->stackedOnMobile();
    }

    protected function getContratosQuery(): Builder
    {
        $idsAcessiveis = auth()->user()->pessoasAcessiveis()->pluck('id');

        return Contrato::query()
            ->where(function (Builder $query) use ($idsAcessiveis) {
                $query->whereHas('matricula', fn (Builder $q) => $q->whereIn('pessoa_id', $idsAcessiveis))
                    ->orWhereHas('responsaveisFinanceiros', fn (Builder $q) => $q->whereIn('pessoa_id', $idsAcessiveis));
            })
            ->with(['matricula.pessoa']);
    }
}
