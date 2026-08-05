<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementStatus;
use App\Http\Resources\ConversationResource;
use App\Models\Announcement;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MessageController extends Controller
{
    /**
     * Démarre une conversation sur une annonce (le client écrit le premier message).
     */
    public function start(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        abort_if($user->isBanned(), 403, 'Compte banni.');

        if ($announcement->status !== AnnouncementStatus::Published->value) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }

        $data = $this->validate($request, [
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if ($announcement->user_id === $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas discuter à propos de votre propre annonce.',
                'code' => 'self_conversation',
            ], 422);
        }

        $conversation = Conversation::firstOrCreate(
            ['announcement_id' => $announcement->id, 'buyer_id' => $user->id],
            ['last_message_at' => now()],
        );

        $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $data['message'],
        ]);
        $conversation->last_message_at = now();
        $conversation->save();

        $conversation->load(['announcement.brand', 'announcement.model', 'announcement.photos', 'announcement.user', 'buyer', 'latestMessage', 'messages']);

        return (new ConversationResource($conversation))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Liste mes conversations (en tant qu'acheteur ou vendeur).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where(fn ($q) => $q->where('buyer_id', $user->id)
                ->orWhereHas('announcement', fn ($q) => $q->where('user_id', $user->id)))
            ->with(['announcement.brand', 'announcement.model', 'announcement.photos', 'announcement.user', 'buyer', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $user->id)])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return ConversationResource::collection($conversations);
    }

    /**
     * Ouvre un fil : renvoie la conversation + messages, et marque comme lus
     * les messages reçus.
     */
    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        $user = $request->user();

        abort_unless($conversation->isParticipant($user), 403);

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->load(['announcement.brand', 'announcement.model', 'announcement.photos', 'announcement.user', 'buyer', 'messages.sender']);

        return new ConversationResource($conversation);
    }

    /**
     * Envoie un message dans un fil existant.
     */
    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_if($user->isBanned(), 403, 'Compte banni.');
        abort_unless($conversation->isParticipant($user), 403);

        $data = $this->validate($request, [
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $data['message'],
        ]);
        $conversation->last_message_at = now();
        $conversation->save();

        return response()->json([
            'message' => 'Message envoyé.',
            'data' => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_id' => $message->sender_id,
                'is_mine' => true,
                'read_at' => null,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Nombre total de messages non lus (pour le badge de la barre de navigation).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = Conversation::query()
            ->where(fn ($q) => $q->where('buyer_id', $user->id)
                ->orWhereHas('announcement', fn ($q) => $q->where('user_id', $user->id)))
            ->get()
            ->sum(fn (Conversation $c) => $c->messages()->where('sender_id', '!=', $user->id)->whereNull('read_at')->count());

        return response()->json(['unread' => $count]);
    }
}