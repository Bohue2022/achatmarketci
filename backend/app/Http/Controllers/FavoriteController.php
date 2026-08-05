<?php

namespace App\Http\Controllers;

use App\Enums\AnnouncementStatus;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = $request->user()->favorites()
            ->with(['announcement.brand', 'announcement.model', 'announcement.city', 'announcement.photos'])
            ->latest()
            ->get()
            ->pluck('announcement')
            ->filter(fn ($a) => $a->status === AnnouncementStatus::Published->value);

        return AnnouncementResource::collection($favorites);
    }

    public function toggle(Request $request, Announcement $announcement): JsonResponse
    {
        if ($announcement->status !== AnnouncementStatus::Published->value) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }

        $user = $request->user();
        $existing = Favorite::where('user_id', $user->id)
            ->where('announcement_id', $announcement->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['is_favorite' => false]);
        }

        $user->favorites()->create(['announcement_id' => $announcement->id]);

        return response()->json(['is_favorite' => true], 201);
    }
}