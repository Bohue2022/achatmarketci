<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isBuyer = $user !== null && $user->id === $this->buyer_id;
        $other = $isBuyer ? $this->announcement->user : $this->buyer;

        return [
            'id' => $this->id,
            'updated_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => (int) ($this->unread_count ?? $this->unreadFor($user)),

            'other_party' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'role' => $other->role->value,
                'is_verified_pro' => (bool) $other->is_verified_pro,
                'company_name' => $other->company_name,
            ] : null,

            'announcement' => $this->whenLoaded('announcement', fn () => [
                'id' => $this->announcement->id,
                'slug' => $this->announcement->slug,
                'title' => $this->announcement->full_title,
                'price_formatted' => $this->announcement->price_formatted,
                'cover' => $this->announcement->photos->first()?->url,
            ]),

            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'body' => $this->latestMessage->body,
                'sender_id' => $this->latestMessage->sender_id,
                'created_at' => $this->latestMessage->created_at?->toIso8601String(),
            ] : null),

            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'sender_id' => $m->sender_id,
                'is_mine' => $user !== null && $user->id === $m->sender_id,
                'read_at' => $m->read_at?->toIso8601String(),
                'created_at' => $m->created_at?->toIso8601String(),
            ])),
        ];
    }
}
