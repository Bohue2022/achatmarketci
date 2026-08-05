<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementStatus;
use App\Http\Requests\ContactRequest;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function send(ContactRequest $request, Announcement $announcement): JsonResponse
    {
        if ($announcement->status !== AnnouncementStatus::Published->value) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }

        // Anti-spam simple : taux d'appels limité via throttle (voir routes)
        $announcement->contacts()->create([
            'buyer_id' => $request->user()?->id,
            'name' => $request->input('name', $request->user()?->name),
            'phone' => $request->input('phone', $request->user()?->phone),
            'email' => $request->input('email'),
            'message' => $request->input('message'),
            'channel' => $request->input('channel'),
        ]);

        $announcement->increment('contacts_count');

        return response()->json([
            'message' => 'Votre message a bien été envoyé au vendeur.',
            'seller_phone' => $announcement->user->phone,
            'seller_whatsapp' => $announcement->user->whatsapp,
        ], 201);
    }
}