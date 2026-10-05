<?php

namespace App\Services;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\User;
use App\Notifications\AcompanhamentoInteressadoNotification;
use App\Notifications\LeadEstagnadoNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Alertas diários de acompanhamento do CRM (`crm:notificar-pendentes`).
 *
 * O comando antigo repetia o mesmo aviso (e-mail + sino + activity log) todo dia enquanto o lead continuava
 * atrasado/estagnado, e ignorava por completo os leads sem consultor — justamente os que entram pelo site.
 * Agora:
 *
 *  - cada lead é avisado ao consultor no máximo uma vez a cada `crm.alertas.intervalo_dias` (coluna
 *    `interessado.ultimo_alerta_em`);
 *  - leads parados há muito tempo (`crm.alertas.escalonar_apos_dias`) vão também à gestão, num único resumo;
 *  - leads ativos sem consultor há mais de `crm.alertas.sem_consultor_apos_horas` entram num resumo diário
 *    à gestão, com atalho para a lista filtrada.
 *
 * "Gestão" = usuários ativos com papel admin ou super_admin.
 */
class AlertaLeadsService
{
    /** Dias sem interação a partir dos quais um lead é considerado estagnado (mesmo valor de `Interessado::estagnados()`). */
    private const DIAS_ESTAGNACAO = 7;

    /** Quantos nomes de lead aparecem no corpo do resumo enviado à gestão. */
    private const NOMES_NO_RESUMO = 5;

    /**
     * @return array{atrasados: int, estagnados: int, escalonados: int, sem_consultor: int}
     */
    public function executar(): array
    {
        $limiteReenvio = now()->subDays(max(1, (int) config('crm.alertas.intervalo_dias', 3)));

        $pendenteDeAviso = fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q
            ->whereNull('ultimo_alerta_em')
            ->orWhere('ultimo_alerta_em', '<=', $limiteReenvio));

        $relacoes = ['pessoa', 'usuario', 'status', 'ultimoHistorico'];

        $atrasados = Interessado::with($relacoes)
            ->precisaContato()
            ->ativos()
            ->whereNotNull('usuario_id')
            ->where($pendenteDeAviso)
            ->get();

        $estagnados = Interessado::with($relacoes)
            ->estagnados(self::DIAS_ESTAGNACAO)
            ->ativos()
            ->whereNotNull('usuario_id')
            ->where($pendenteDeAviso)
            ->whereNotIn('id', $atrasados->pluck('id'))
            ->get();

        $avisados = 0;
        $escalonados = collect();
        $escalonarApos = max(1, (int) config('crm.alertas.escalonar_apos_dias', 7));

        foreach ($atrasados as $interessado) {
            if ($this->notificarAtraso($interessado)) {
                $avisados++;
                $this->marcarAvisado($interessado);

                if ($interessado->data_proximo_contato->lte(now()->subDays($escalonarApos))) {
                    $escalonados->push($interessado);
                }
            }
        }

        $estagnadosAvisados = 0;

        foreach ($estagnados as $interessado) {
            if ($this->notificarEstagnacao($interessado)) {
                $estagnadosAvisados++;
                $this->marcarAvisado($interessado);

                if ($interessado->diasSemInteracao() >= self::DIAS_ESTAGNACAO + $escalonarApos) {
                    $escalonados->push($interessado);
                }
            }
        }

        $semConsultor = Interessado::query()
            ->ativos()
            ->whereNull('usuario_id')
            ->where('created_at', '<=', now()->subHours(max(1, (int) config('crm.alertas.sem_consultor_apos_horas', 24))))
            ->count();

        $this->notificarGestao($escalonados, $semConsultor);

        return [
            'atrasados' => $avisados,
            'estagnados' => $estagnadosAvisados,
            'escalonados' => $escalonados->count(),
            'sem_consultor' => $semConsultor,
        ];
    }

    private function notificarAtraso(Interessado $interessado): bool
    {
        $consultor = $interessado->usuario;

        if (! $consultor instanceof User) {
            return false;
        }

        $consultor->notify(new AcompanhamentoInteressadoNotification($interessado));

        Notification::make()
            ->title('Follow-up Pendente')
            ->body("O lead {$interessado->pessoa->nome} precisa de contato. Agendado para {$interessado->data_proximo_contato->format('d/m/Y H:i')}.")
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->actions([
                Action::make('view')
                    ->label('Ver Lead')
                    ->url(InteressadoResource::getUrl('edit', ['record' => $interessado]))
                    ->button(),
            ])
            ->sendToDatabase($consultor);

        activity('crm')
            ->performedOn($interessado)
            ->causedByAnonymous()
            ->withProperties([
                'tipo' => 'notificacao_follow_up_automatica',
                'consultor_id' => $consultor->id,
                'consultor_nome' => $consultor->name,
                'interessado_nome' => $interessado->pessoa->nome,
            ])
            ->log("Notificação automática de follow-up enviada para {$consultor->name}");

        return true;
    }

    private function notificarEstagnacao(Interessado $interessado): bool
    {
        $consultor = $interessado->usuario;

        if (! $consultor instanceof User) {
            return false;
        }

        $dias = $interessado->diasSemInteracao();

        $consultor->notify(new LeadEstagnadoNotification($interessado, $dias));

        Notification::make()
            ->title('Lead Estagnado')
            ->body("O lead {$interessado->pessoa->nome} está há {$dias} dias sem qualquer interação registrada.")
            ->icon('heroicon-o-exclamation-circle')
            ->color('danger')
            ->actions([
                Action::make('view')
                    ->label('Ver Lead')
                    ->url(InteressadoResource::getUrl('edit', ['record' => $interessado]))
                    ->button(),
            ])
            ->sendToDatabase($consultor);

        activity('crm')
            ->performedOn($interessado)
            ->causedByAnonymous()
            ->withProperties([
                'tipo' => 'notificacao_estagnacao_automatica',
                'consultor_id' => $consultor->id,
                'consultor_nome' => $consultor->name,
                'interessado_nome' => $interessado->pessoa->nome,
                'dias_sem_interacao' => $dias,
            ])
            ->log("Notificação automática de estagnação enviada para {$consultor->name}");

        return true;
    }

    /**
     * Grava a data do aviso sem passar pelos eventos do Eloquent: nem activity log nem `updated_at`
     * devem mudar só porque o sistema avisou o consultor.
     */
    private function marcarAvisado(Interessado $interessado): void
    {
        DB::table('interessado')->where('id', $interessado->id)->update(['ultimo_alerta_em' => now()]);
    }

    /**
     * Resumo único (não um aviso por lead) para a gestão: leads parados há muito tempo e leads sem consultor.
     * No máximo um por dia, mesmo que o comando seja executado mais de uma vez.
     *
     * @param  Collection<int, Interessado>  $escalonados
     */
    private function notificarGestao(Collection $escalonados, int $semConsultor): void
    {
        if ($escalonados->isEmpty() && $semConsultor === 0) {
            return;
        }

        if (! Cache::add('crm:alertas:gestao:'.now()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        $gestores = User::role(['admin', 'super_admin'])->ativos()->get();

        if ($gestores->isEmpty()) {
            return;
        }

        if ($escalonados->isNotEmpty()) {
            $nomes = $escalonados->take(self::NOMES_NO_RESUMO)->map(fn (Interessado $i): string => (string) $i->pessoa?->nome)->filter()->implode(', ');
            $restantes = $escalonados->count() - self::NOMES_NO_RESUMO;

            Notification::make()
                ->title('Leads parados exigem atenção da gestão')
                ->body("{$escalonados->count()} lead(s) seguem atrasados ou sem interação há muito tempo mesmo após o aviso aos consultores: {$nomes}".($restantes > 0 ? " e mais {$restantes}." : '.'))
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->actions([
                    Action::make('ver')
                        ->label('Ver leads que precisam de contato')
                        ->url(InteressadoResource::getUrl('index', ['activeTab' => 'precisa_contato']))
                        ->button(),
                ])
                ->sendToDatabase($gestores);
        }

        if ($semConsultor > 0) {
            Notification::make()
                ->title('Leads sem consultor responsável')
                ->body("{$semConsultor} lead(s) ativo(s) ainda não têm consultor e, por isso, não geram alertas de acompanhamento. Atribua um consultor para que sejam atendidos.")
                ->icon('heroicon-o-user-plus')
                ->color('warning')
                ->actions([
                    Action::make('ver')
                        ->label('Ver leads sem consultor')
                        ->url(InteressadoResource::getUrl('index', ['tableFilters' => ['sem_consultor' => ['value' => 1]]]))
                        ->button(),
                ])
                ->sendToDatabase($gestores);
        }
    }
}
