<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Support\Facades\Cache;

trait HasNavigationBadge
{
    public static function getNavigationBadge(): ?string
    {
        try {
            $user = auth()->user();
            $cacheKey = 'nav_badge_'.static::class.'_'.($user ? $user->id : 'guest');

            return (string) Cache::remember($cacheKey, now()->addMinutes(2), function () {
                return (string) static::getEloquentQuery()->count();
            });
        } catch (\Throwable $th) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
