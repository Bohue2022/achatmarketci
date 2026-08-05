<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'is_verified_pro',
        'company_name', 'company_logo_path', 'city_id', 'bio', 'avatar_path',
        'whatsapp', 'banned_at', 'ban_reason', 'kyc_verified_at', 'rccm_number',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified_pro' => 'boolean',
            'banned_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'role' => \App\Enums\Role::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === \App\Enums\Role::Admin;
    }

    public function isModerator(): bool
    {
        return in_array($this->role, [\App\Enums\Role::Admin, \App\Enums\Role::Moderator], true);
    }

    public function isPro(): bool
    {
        return $this->role === \App\Enums\Role::Pro;
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null && $this->banned_at->isPast();
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function activeAnnouncements(): HasMany
    {
        return $this->hasMany(Announcement::class)->active()->latest('published_at');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function conversationsAsBuyer(): HasMany
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Quota d'annonces actives.
     * Lancement : entièrement gratuit → illimité pour tous.
     * (La monétisation par abonnement reviendra plus tard.)
     */
    public function activeAnnouncementLimit(): ?int
    {
        return null;
    }

    /** Durée de vie d'une annonce (jours) selon l'abonnement. */
    public function listingDurationDays(): int
    {
        return optional($this->activeSubscription)->plan?->listing_duration_days ?? 30;
    }
}