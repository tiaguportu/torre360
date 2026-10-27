<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\UserSession;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class ControleSessoes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static UnitEnum|string|null $navigationGroup = 'Sistema e Segurança';

    protected static ?string $navigationLabel = 'Sessões Ativas';

    protected static ?string $title = 'Controle de Sessões Ativas';

    protected static ?string $slug = 'sistema/sessoes-ativas';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.controle-sessoes';

    public static function canAccess(): bool
    {
        return auth()->check() && (bool) auth()->user()?->is_active;
    }

    /**
     * Resumo das sessões para exibição nos cards da página.
     *
     * @return array<string, mixed>
     */
    public function getResumo(): array
    {
        $user = auth()->user();
        $ehAdmin = $user?->hasRole('super_admin') || (bool) $user?->can('ViewAny:User');

        $minhasSessoes = UserSession::where('user_id', $user?->id)->get();
        $currentSessionId = request()->hasSession() ? request()->session()->getId() : null;
        $sessaoAtual = $minhasSessoes->firstWhere('id', $currentSessionId);

        $dados = [
            'total_minhas_sessoes' => $minhasSessoes->count(),
            'outros_dispositivos' => max(0, $minhasSessoes->where('id', '!=', $currentSessionId)->count()),
            'ip_atual' => request()->ip(),
            'dispositivo_atual' => $sessaoAtual?->device_label ?? 'Dispositivo Atual',
            'eh_admin' => $ehAdmin,
        ];

        if ($ehAdmin) {
            $dados['total_sessoes_sistema'] = UserSession::whereNotNull('user_id')->count();
            $dados['usuarios_conectados'] = UserSession::whereNotNull('user_id')->distinct('user_id')->count('user_id');
        }

        return $dados;
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $podeVerTodos = $user?->hasRole('super_admin') || (bool) $user?->can('ViewAny:User');

        return $table
            ->query(function () use ($user, $podeVerTodos): Builder {
                $query = UserSession::query()
                    ->with('user')
                    ->whereNotNull('user_id')
                    ->ativasRecentes();

                if (! $podeVerTodos) {
                    $query->where('user_id', $user?->id);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('status')
                    ->label('Tipo de Sessão')
                    ->state(fn (UserSession $record) => $record->is_current ? 'Esta Sessão (Atual)' : 'Sessão Remota')
                    ->badge()
                    ->color(fn (UserSession $record) => $record->is_current ? 'success' : 'gray')
                    ->icon(fn (UserSession $record) => $record->is_current ? 'heroicon-m-check-circle' : 'heroicon-m-device-phone-mobile'),

                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable()
                    ->visible($podeVerTodos)
                    ->description(fn (UserSession $record) => $record->user?->email ?? 'Sem e-mail'),

                TextColumn::make('device_label')
                    ->label('Dispositivo e Navegador')
                    ->icon(fn (UserSession $record) => $record->is_mobile ? 'heroicon-o-device-phone-mobile' : 'heroicon-o-computer-desktop')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('user_agent', 'like', "%{$search}%")),

                TextColumn::make('ip_address')
                    ->label('Endereço IP')
                    ->searchable(),

                TextColumn::make('last_activity')
                    ->label('Última Atividade')
                    ->state(fn (UserSession $record) => $record->last_active_at->diffForHumans())
                    ->description(fn (UserSession $record) => $record->last_active_at->format('d/m/Y H:i:s'))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('last_activity', $direction)),
            ])
            ->filters([
                Filter::make('apenas_minhas')
                    ->label('Apenas Minhas Sessões')
                    ->visible($podeVerTodos)
                    ->query(fn (Builder $query) => $query->where('user_id', auth()->id())),

                SelectFilter::make('user_id')
                    ->label('Filtrar por Usuário')
                    ->visible($podeVerTodos)
                    ->options(fn () => User::query()->ativos()->orderBy('name')->pluck('name', 'id')),
            ])
            ->actions([
                Action::make('revogar')
                    ->label('Revogar')
                    ->icon('heroicon-o-power')
                    ->color('danger')
                    ->tooltip('Desconectar este dispositivo')
                    ->requiresConfirmation()
                    ->modalHeading('Revogar Sessão Remota')
                    ->modalDescription('O dispositivo associado a esta sessão será desconectado imediatamente do Torre360.')
                    ->modalSubmitActionLabel('Confirmar e Revogar')
                    ->hidden(fn (UserSession $record): bool => (bool) $record->is_current)
                    ->action(function (UserSession $record) {
                        $record->revogar(auth()->user());

                        Notification::make()
                            ->title('Sessão revogada com sucesso!')
                            ->body('O dispositivo remoto foi desconectado.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('revogarSelecionadas')
                    ->label('Revogar Selecionadas')
                    ->icon('heroicon-o-power')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revogar Sessões Selecionadas')
                    ->modalDescription('Todas as sessões selecionadas (exceto a sessão atual do seu navegador) serão desconectadas.')
                    ->modalSubmitActionLabel('Revogar Todas Selecionadas')
                    ->action(function (Collection $records) {
                        $currentId = request()->hasSession() ? request()->session()->getId() : null;
                        $revogadas = 0;

                        /** @var UserSession $record */
                        foreach ($records as $record) {
                            if ($record->id !== $currentId) {
                                $record->revogar(auth()->user());
                                $revogadas++;
                            }
                        }

                        Notification::make()
                            ->title("{$revogadas} sessões foram revogadas com sucesso!")
                            ->success()
                            ->send();
                    }),
            ])
            ->stackedOnMobile();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('desconectarOutrasSessoes')
                ->label('Desconectar Outras Sessões')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Desconectar todos os outros dispositivos?')
                ->modalDescription('Você continuará conectado neste navegador atual. Todas as outras sessões da sua conta abertas em outros computadores, tablets ou smartphones serão encerradas imediatamente.')
                ->modalSubmitActionLabel('Sim, Desconectar Outros')
                ->action(function () {
                    $user = auth()->user();
                    $currentId = request()->hasSession() ? request()->session()->getId() : null;

                    $query = UserSession::with('user')->where('user_id', $user?->id);

                    if ($currentId) {
                        $query->where('id', '!=', $currentId);
                    }

                    $sessoes = $query->get();
                    $total = 0;

                    foreach ($sessoes as $sessao) {
                        $sessao->revogar($user);
                        $total++;
                    }

                    Notification::make()
                        ->title("{$total} outros dispositivos foram desconectados com sucesso!")
                        ->success()
                        ->send();
                }),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Controle de Sessões Ativas')
                ->modalWidth('3xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $papel = session('active_role') ?? $user?->roles->first()?->name ?? 'usuário';
        $podeVerTodos = $user?->hasRole('super_admin') || (bool) $user?->can('ViewAny:User');

        $html = '<p>O <strong>Painel de Controle de Sessões Ativas</strong> permite monitorar e proteger o acesso à sua conta, identificando todos os dispositivos (computadores, celulares e tablets) que possuem acesso conectado ao sistema.</p>';

        $html .= '<h4>Funcionalidades Disponíveis para Você (Papel: '.htmlspecialchars((string) $papel).'):</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>Identificação do Dispositivo Atual:</strong> A sessão que você está usando agora é sinalizada com o selo verde <em>"Esta Sessão (Atual)"</em>.</li>';
        $html .= '<li><strong>Revogação Individual:</strong> Você pode clicar no botão <em>"Revogar"</em> em qualquer outra sessão para desconectar aquele aparelho imediatamente.</li>';
        $html .= '<li><strong>Desconectar Outras Sessões:</strong> Use o botão vermelho no topo da página para desconectar de uma só vez todos os outros aparelhos onde sua conta estiver aberta, mantendo apenas este dispositivo conectado.</li>';

        if ($podeVerTodos) {
            $html .= '<li><strong>Gestão Administrativa (Super Admin / Gestão de Usuários):</strong> Você possui permissão para visualizar as sessões de todos os colaboradores da escola, filtrar por usuário e revogar sessões remotamente em caso de demissão, suspeita de invasão ou extravio de equipamento corporativo.</li>';
        }

        $html .= '</ul>';

        $html .= '<p class="text-sm text-gray-500 mt-2">Dica de Segurança: Se você notar alguma sessão em uma cidade, IP ou aparelho desconhecido, revogue-a imediatamente e altere a sua senha no seu perfil.</p>';

        return $html;
    }
}
