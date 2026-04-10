<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'url',
        'ip_address',
        'user_agent',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public static function log(?string $event = null, ?string $auditableType = null, ?int $auditableId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        if (! auth()->check()) {
            return;
        }

        self::create([
            'user_id' => auth()->id(),
            'event' => $event ?? 'view',
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
