<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $table = 'sessions';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeDoUsuario(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAtivasRecentes(Builder $query): Builder
    {
        return $query->orderByDesc('last_activity');
    }

    /**
     * Identifica se esta sessão é a sessão atual ativa na requisição.
     */
    protected function isCurrent(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => request()->hasSession() && $this->id === request()->session()->getId()
        );
    }

    /**
     * Retorna a data e hora da última atividade como instância Carbon.
     */
    protected function lastActiveAt(): Attribute
    {
        return Attribute::make(
            get: fn (): Carbon => Carbon::createFromTimestamp((int) $this->last_activity)
        );
    }

    /**
     * Identifica o sistema operacional / plataforma a partir do User-Agent.
     */
    protected function platform(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $agent = (string) ($this->user_agent ?? '');

                return match (true) {
                    str_contains($agent, 'Windows') => 'Windows',
                    str_contains($agent, 'iPhone') => 'iOS (iPhone)',
                    str_contains($agent, 'iPad') => 'iOS (iPad)',
                    str_contains($agent, 'Android') => 'Android',
                    str_contains($agent, 'Macintosh') || str_contains($agent, 'Mac OS X') => 'macOS',
                    str_contains($agent, 'Linux') => 'Linux',
                    default => 'Desconhecido',
                };
            }
        );
    }

    /**
     * Identifica o navegador web a partir do User-Agent.
     */
    protected function browser(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $agent = (string) ($this->user_agent ?? '');

                return match (true) {
                    str_contains($agent, 'Edg/') => 'Microsoft Edge',
                    str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
                    str_contains($agent, 'Chrome/') => 'Google Chrome',
                    str_contains($agent, 'Firefox/') => 'Mozilla Firefox',
                    str_contains($agent, 'Safari/') && ! str_contains($agent, 'Chrome') => 'Apple Safari',
                    default => 'Navegador Web',
                };
            }
        );
    }

    /**
     * Identifica se o dispositivo é móvel (smartphone/tablet).
     */
    protected function isMobile(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $agent = (string) ($this->user_agent ?? '');

                return (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $agent);
            }
        );
    }

    /**
     * Rótulo consolidado para exibição rápida.
     */
    protected function deviceLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "{$this->browser} no {$this->platform}".($this->is_mobile ? ' (Mobile)' : ' (Desktop)')
        );
    }

    /**
     * Revoga a sessão, excluindo o registro do banco de dados e registrando a auditoria.
     */
    public function revogar(?User $autor = null): bool
    {
        $autor = $autor ?? auth()->user();

        if (! $this->relationLoaded('user') && $this->user_id) {
            $this->load('user');
        }

        $dono = $this->relationLoaded('user') ? $this->user : null;
        $ip = $this->ip_address;
        $label = $this->device_label;

        $deletado = (bool) $this->delete();

        if ($deletado) {
            activity('auth')
                ->causedBy($autor)
                ->withProperties([
                    'session_id' => $this->id,
                    'user_id' => $dono?->id,
                    'user_email' => $dono?->email,
                    'ip_address' => $ip,
                    'dispositivo' => $label,
                ])
                ->log("Sessão remota ({$label} - IP {$ip}) do usuário {$dono?->name} foi revogada por {$autor?->name}.");
        }

        return $deletado;
    }
}
