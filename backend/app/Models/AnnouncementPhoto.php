<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['announcement_id', 'path', 'disk', 'position', 'is_cover'];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function getUrlAttribute(): string
    {
        $disk = \Illuminate\Support\Facades\Storage::disk($this->disk);
        return $disk->url($this->path);
    }
}