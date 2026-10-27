<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'email_verified_at',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'telegram_link_token',
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
            'password' => 'hashed',
            'balance' => 'decimal:2',
            'banned_until' => 'datetime',
            'notification_prefs' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'moderator']);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function sharedServers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class);
    }

    public function canManage(Server $server): bool
    {
        return $server->user_id === $this->id
            || $server->coOwners()->whereKey($this->id)->exists();
    }

    public function isBanned(): bool
    {
        return $this->banned_until?->isFuture() ?? false;
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(['admin', 'moderator']);
    }

    /**
     * Канали для події з урахуванням налаштувань користувача (за замовчуванням — email).
     *
     * @return list<string> mail | telegram
     */
    public function channelsFor(string $event): array
    {
        $channels = $this->notification_prefs[$event] ?? config("graj.notifications.events.{$event}", ['mail']);

        if (! $this->telegram_chat_id) {
            $channels = array_diff($channels, ['telegram']);
        }

        return array_values($channels);
    }
}
