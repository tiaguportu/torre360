<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SituacaoConcessaoFerias;
use App\Filament\Pages\PainelFerias;
use App\Models\User;
use App\Services\FeriasVencimentoService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Resumo semanal para quem cuida do RH: quantas férias estão vencidas, com o prazo acabando ou sem registro. A
 * mensagem só traz contagens e o link do painel, sem nome de empregado (o aviso vai por e-mail e push).
 */
class AvisarFeriasCommand extends Command
{
    protected $signature = 'rh:avisar-ferias';

    protected $description = 'Avisa a administração sobre férias vencidas, com prazo acabando ou sem registro';

    public function handle(FeriasVencimentoService $servico): int
    {
        Cache::forget(PainelFerias::CACHE_SELO);

        $resumo = $servico->resumo();
        $vencidas = $resumo[SituacaoConcessaoFerias::Vencida->value];
        $aVencer = $resumo[SituacaoConcessaoFerias::AVencer->value];
        $semRegistro = $resumo[SituacaoConcessaoFerias::SemRegistro->value];

        if ($vencidas + $aVencer + $semRegistro === 0) {
            $this->info('Nenhuma férias exige atenção.');

            return self::SUCCESS;
        }

        $destinatarios = Role::where('name', 'super_admin')->where('guard_name', 'web')->exists()
            ? User::role('super_admin')->get()
            : new Collection;

        if (Permission::where('name', 'VerPainelFerias:Funcionario')->where('guard_name', 'web')->exists()) {
            $destinatarios = $destinatarios->merge(User::permission('VerPainelFerias:Funcionario')->get())->unique('id');
        }

        NotificationService::sendToMany($destinatarios, 'Férias que exigem atenção', [
            'body' => "{$vencidas} vencida(s) (risco de pagamento em dobro), {$aVencer} com o prazo de concessão acabando e {$semRegistro} período(s) sem registro.",
            'action_url' => url('/admin/rh/painel-ferias'),
            'action_label' => 'Abrir o painel de férias',
            'type' => $vencidas > 0 ? 'danger' : 'warning',
        ]);

        $this->info("Aviso enviado a {$destinatarios->count()} usuário(s): {$vencidas} vencida(s), {$aVencer} a vencer, {$semRegistro} sem registro.");

        return self::SUCCESS;
    }
}
