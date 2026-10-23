<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Services\Pendencias\MesaDePendencias as Mesa;
use App\Support\HelpContent;
use App\Support\Pendencias\FilaDePendencias;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Mesa de pendências por papel (Onda 71): as filas que hoje ficam espalhadas pelos módulos (cancelamentos para analisar,
 * documentos para assinar, rematrículas sem turma, providências de RH vencidas, grades incompletas...) numa tela só, com o
 * que é do papel ativo do usuário. A Mesa só lê: cada item leva à tela onde a pendência se resolve.
 */
class MesaDePendencias extends Page
{
    use HasAjudaAction;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?int $navigationSort = -1;

    protected static ?string $title = 'Minha Mesa';

    protected static ?string $navigationLabel = 'Minha Mesa';

    protected static ?string $slug = 'minha-mesa';

    protected string $view = 'filament.pages.mesa-de-pendencias';

    /**
     * Quem enxerga ao menos uma fila (pelas permissões do papel ativo) vê a Mesa. Sem fila nenhuma, o item nem aparece no
     * menu.
     */
    public static function canAccess(): bool
    {
        $usuario = auth()->user();

        return $usuario !== null && app(Mesa::class)->temAlgumaFila($usuario);
    }

    /**
     * Itens atrasados (ou, não havendo, o total) no menu, guardados por alguns minutos.
     */
    public static function getNavigationBadge(): ?string
    {
        $usuario = auth()->user();

        if (! $usuario || ! static::canAccess()) {
            return null;
        }

        $resumo = app(Mesa::class)->resumo($usuario);
        $numero = $resumo['atrasados'] > 0 ? $resumo['atrasados'] : $resumo['total'];

        return $numero > 0 ? (string) $numero : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $usuario = auth()->user();

        return $usuario && app(Mesa::class)->resumo($usuario)['atrasados'] > 0 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $usuario = auth()->user();

        if (! $usuario || ! static::canAccess()) {
            return null;
        }

        $resumo = app(Mesa::class)->resumo($usuario);

        return "{$resumo['total']} item(ns) esperando ação, {$resumo['atrasados']} atrasado(s)";
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return Collection<int, FilaDePendencias>
     */
    public function getFilas(): Collection
    {
        return app(Mesa::class)->filas(auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('atualizar')
                ->label('Atualizar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => app(Mesa::class)->esquecer(auth()->user())),
            $this->ajudaAction('Minha Mesa', HelpContent::make('📥', 'Minha Mesa', 'O que está esperando uma ação sua, reunido numa tela só.')
                ->passos('🔁 Como usar', [
                    'Comece pelo vermelho: o número vermelho de cada fila é o que já passou do prazo.',
                    'Clique em um item para ir direto à tela onde ele se resolve, ou em "Abrir a fila" para ver todos.',
                    'Resolvido o item, ele sai da fila sozinho. Use "Atualizar" se a contagem do menu ainda mostrar o número antigo.',
                ])
                ->secao('📌 Bom saber', [
                    ['👤', 'Por papel', 'Cada pessoa vê só as filas que o papel ativo dela pode resolver. Ao trocar de papel, a Mesa muda.'],
                    ['⏱️', 'Prazos', 'Alguns prazos vêm do próprio item (data de saída, prazo da providência); os demais são limites da escola, como pedido de cancelamento sem resposta há mais de 3 dias.'],
                    ['📄', 'Só leitura', 'A Mesa não muda nada: ela conta e leva até a tela certa.'],
                ])
                ->dica('Quando uma fila não aparece, é porque o seu papel não tem permissão para ela ou porque ela está em dia.')),
        ];
    }
}
