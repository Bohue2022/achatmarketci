<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion back-office de toutes les annonces (tous statuts).
 */
class AdminAnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::with(['brand', 'model', 'city', 'commune', 'photos', 'user'])
            ->withCount(['photos', 'moderationActions']);

        if ($request->filled('status') && in_array($request->input('status'), AnnouncementStatus::values(), true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($b) use ($q) {
                $b->where('title', 'like', "%{$q}%")
                    ->orWhere('full_title', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('company_name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('seller_type') && in_array($request->input('seller_type'), ['user', 'pro'], true)) {
            $query->whereHas('user', fn ($u) => $u->where('role', $request->input('seller_type')));
        }

        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'expensive' => $query->orderByDesc('price'),
            'cheap' => $query->orderBy('price'),
            default => $query->latest(),
        };

        $announcements = $query->paginate((int) $request->input('per_page', 15));

        $counts = [];
        foreach (AnnouncementStatus::cases() as $status) {
            $counts[$status->value] = Announcement::where('status', $status->value)->count();
        }

        return response()->json([
            'data' => AnnouncementResource::collection($announcements),
            'counts' => $counts,
            'meta' => [
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
                'total' => $announcements->total(),
            ],
        ]);
    }
}