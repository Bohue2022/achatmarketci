<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'brand_id', 'model_id', 'city_id', 'commune_id',
        'title', 'slug', 'description', 'price', 'currency',
        'year', 'mileage', 'fuel_type', 'transmission', 'condition',
        'body_type', 'is_dedouane', 'has_grise', 'origin',
        'engine_cc', 'power_hp', 'doors', 'seats', 'number_of_owners',
        'status', 'rejection_reason', 'published_at', 'expires_at', 'moderated_at',
        'views_count', 'contacts_count', 'featured', 'boosted', 'boost_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_dedouane' => 'boolean',
            'has_grise' => 'boolean',
            'featured' => 'boolean',
            'boosted' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'moderated_at' => 'datetime',
            'boost_expires_at' => 'datetime',
            'equipment' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(City::class, 'commune_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AnnouncementPhoto::class)->orderBy('position');
    }

    public function coverPhoto()
    {
        return $this->hasOne(AnnouncementPhoto::class)->where('is_cover', true)
            ->orWhere('position', 0)
            ->orderBy('position');
    }

    public function moderationActions(): HasMany
    {
        return $this->hasMany(ModerationAction::class)->latest();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(AnnouncementReport::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(AnnouncementContact::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function scopeStatus($q, $status)
    {
        return $q->where('status', $status);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function getFullTitleAttribute(): string
    {
        $brand = $this->brand->name ?? '';
        $model = $this->model->name ?? '';
        $year = $this->year ? (string) $this->year : '';

        return trim("{$brand} {$model} {$year}");
    }

    public function getPriceFormattedAttribute(): string
    {
        return number_format($this->price, 0, ',', ' ') . ' FCFA';
    }
}