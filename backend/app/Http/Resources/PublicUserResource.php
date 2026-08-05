<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Résolution explicite via le garde Sanctum : cette route est publique et
        // $request->user() ne résoudrait pas le token sans middleware auth.
        $viewer = Auth::guard('sanctum')->user();

        // Le contact n'est révélé que si le visiteur partage déjà une conversation
        // avec cette personne (confidentialité).
        $sharesConversation = $viewer !== null && Conversation::query()
            ->where(fn ($q) => $q->where('buyer_id', $this->id)
                ->orWhereHas('announcement', fn ($q) => $q->where('user_id', $this->id)))
            ->where(fn ($q) => $q->where('buyer_id', $viewer->id)
                ->orWhereHas('announcement', fn ($q) => $q->where('user_id', $viewer->id)))
            ->exists();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role->value,
            'is_verified_pro' => (bool) $this->is_verified_pro,
            'company_name' => $this->company_name,
            'city' => $this->whenLoaded('city', fn () => $this->city?->name),
            'bio' => $this->bio,
            'avatar' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'member_since' => $this->created_at?->toIso8601String(),
            'published_announcements_count' => (int) ($this->active_announcements_count ?? 0),
            'contact' => $sharesConversation ? [
                'phone' => $this->phone,
                'whatsapp' => $this->whatsapp,
            ] : null,
            'published_announcements' => $this->whenLoaded('activeAnnouncements', fn () => $this->activeAnnouncements->map(fn ($a) => [
                'id' => $a->id,
                'slug' => $a->slug,
                'title' => $a->full_title,
                'price_formatted' => $a->price_formatted,
                'city' => $a->city?->name,
                'cover' => $a->photos->first()?->url,
            ])),
        ];
    }
}