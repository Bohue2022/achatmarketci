<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'full_title' => $this->full_title,
            'description' => $this->description,
            'price' => $this->price,
            'price_formatted' => $this->price_formatted,
            'currency' => $this->currency,
            'year' => $this->year,
            'mileage' => $this->mileage,
            'fuel_type' => $this->fuel_type,
            'transmission' => $this->transmission,
            'condition' => $this->condition,
            'body_type' => $this->body_type,
            'is_dedouane' => (bool) $this->is_dedouane,
            'has_grise' => (bool) $this->has_grise,
            'origin' => $this->origin,
            'engine_cc' => $this->engine_cc,
            'power_hp' => $this->power_hp,
            'doors' => $this->doors,
            'seats' => $this->seats,
            'number_of_owners' => $this->number_of_owners,
            'equipment' => $this->equipment,
            'status' => $this->status,
            // Motif visible : modérateur (back-office) ou propriétaire de l'annonce
            'rejection_reason' => $this->when(
                ($request->user()?->isModerator() || $request->user()?->id === $this->user_id),
                $this->rejection_reason
            ),
            'featured' => (bool) $this->featured,
            'boosted' => (bool) $this->boosted,
            'views_count' => $this->views_count,
            'contacts_count' => $this->contacts_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'brand' => $this->whenLoaded('brand', fn () => ['id' => $this->brand->id, 'name' => $this->brand->name]),
            'model' => $this->whenLoaded('model', fn () => ['id' => $this->model->id, 'name' => $this->model->name]),
            'city' => $this->whenLoaded('city', fn () => ['id' => $this->city->id, 'name' => $this->city->name]),
            'commune' => $this->whenLoaded('commune', fn () => $this->commune ? ['id' => $this->commune->id, 'name' => $this->commune->name] : null),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($p) => [
                'id' => $p->id,
                'url' => $p->url,
                'is_cover' => (bool) $p->is_cover,
                'position' => $p->position,
            ])),
            'seller' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'role' => $this->user->role->value,
                'is_verified_pro' => (bool) $this->user->is_verified_pro,
                'company_name' => $this->user->company_name,
                'phone' => $this->user->phone,
                'whatsapp' => $this->user->whatsapp,
                'city' => $this->user->city?->name,
            ]),
        ];

        return $data;
    }
}