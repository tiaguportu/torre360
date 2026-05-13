<?php

namespace App\Filament\Widgets;

use App\Models\Questionario;
use App\Models\QuestionarioResposta;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class QuestionariosPendentes extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = -5;

    protected string $view = 'filament.widgets.questionarios-pendentes';

    protected int|string|array $columnSpan = 'full';

    public function getQuestionarios()
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        return Questionario::query()
            ->where('is_ativo', true)
            ->where(function ($q) {
                $q->whereNull('inicio_aplicacao')->orWhere('inicio_aplicacao', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('fim_aplicacao')->orWhere('fim_aplicacao', '>=', now());
            })
            ->get()
            ->filter(fn ($q) => $q->podeSerRespondidoPor($user))
            ->filter(function ($q) use ($user) {
                // Se o questionário não for anônimo, verificamos se o usuário logado já respondeu
                if (! $q->is_anonimo) {
                    return ! QuestionarioResposta::where('questionario_id', $q->id)
                        ->where('user_id', $user->id)
                        ->where('status', 'enviado')
                        ->exists();
                }

                // Para questionários anônimos, mostramos sempre (pois não rastreamos o usuário)
                return true;
            });
    }

    public static function canView(): bool
    {
        // 1. Verificar permissão do Shield (widget_QuestionariosPendentes)
        if (method_exists(static::class, 'hasWidgetShield') && ! static::hasWidgetShield()) {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        // 2. Verificar se há questionários pendentes
        return (new static)->getQuestionarios()->isNotEmpty();
    }
}
