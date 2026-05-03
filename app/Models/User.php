<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    use HasRoles {
        hasPermissionTo as traitHasPermissionTo;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'activated_at', 'deactivated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Permitir acesso se o e-mail não estiver verificado (para ver o aviso de verificação)
        // Se já estiver verificado, o acesso depende da validade da ativação.
        if ($this->hasVerifiedEmail()) {
            return (bool) $this->is_active;
        }

        return true;
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isActive(): Attribute
    {
        return Attribute::get(function (): bool {
            $now = now();

            return $this->activated_at !== null &&
                   $this->activated_at <= $now &&
                   ($this->deactivated_at === null || $this->deactivated_at > $now);
        });
    }

    public function pessoas(): BelongsToMany
    {
        return $this->belongsToMany(Pessoa::class, 'pessoa_user', 'user_id', 'pessoa_id');
    }

    public function getPessoaAttribute(): ?Pessoa
    {
        return $this->pessoas->first();
    }

    public function getActiveRoleAttribute(): ?string
    {
        if (! session()->has('active_role')) {
            $role = $this->roles->first()?->name;
            if ($role) {
                session(['active_role' => $role]);
            }
        }

        return session('active_role');
    }

    public function hasActiveRole(string|array $role): bool
    {
        $activeRole = $this->active_role;

        if (is_array($role)) {
            return in_array($activeRole, $role);
        }

        return $activeRole === $role;
    }

    public function hasPermissionInActiveRole(string $permission): bool
    {
        return $this->hasPermissionTo($permission);
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // Se for super_admin, sempre tem permissão
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $activeRole = session('active_role');

        if ($activeRole) {
            $role = $this->roles->where('name', $activeRole)->first();

            return $role ? $role->hasPermissionTo($permission, $guardName) : false;
        }

        return $this->traitHasPermissionTo($permission, $guardName);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'activated_at',
        'deactivated_at',
        'fcm_token',
        'device_type',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
