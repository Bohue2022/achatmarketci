<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ModerateRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\ModerationAction;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    public function __construct(private readonly AnnouncementService $service)
    {
    }

    /**
     * File d'attente de modération.
     */
    public function queue(Request $request): JsonResponse
    {
        $query = Announcement::where('status', AnnouncementStatus::Pending->value)
            ->with(['brand', 'model', 'city', 'commune', 'photos', 'user'])
            ->withCount(['photos', 'moderationActions']);

        // Historique de l'utilisateur : priorité aux comptes pro de confiance ? on remonte en premier les particuliers.
        if ($request->filled('priority') && in_array($request->input('priority'), ['user', 'pro', 'newest', 'oldest'], true)) {
            $query->getQuery()->orders = [];
            match ($request->input('priority')) {
                'pro' => $query->whereHas('user', fn ($q) => $q->where('role', 'pro')),
                'user' => $query->whereHas('user', fn ($q) => $q->where('role', 'user')),
                'oldest' => $query->orderBy('created_at'),
                default => $query->orderByDesc('created_at'),
            };
        } else {
            $query->orderByDesc('created_at');
        }

        $announcements = $query->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => AnnouncementResource::collection($announcements),
            'counts' => [
                'pending' => Announcement::where('status', AnnouncementStatus::Pending->value)->count(),
            ],
        ]);
    }

    /**
     * Vue détaillée d'une annonce en modération.
     */
    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        $announcement->load([
            'brand', 'model', 'city', 'commune', 'photos', 'user.city',
            'moderationActions.moderator',
        ]);

        $history = [
            'total_published' => $announcement->user->announcements()->where('status', AnnouncementStatus::Published->value)->count(),
            'past_rejections' => $announcement->user->announcements()->where('status', AnnouncementStatus::Rejected->value)->count(),
            'open_reports' => $announcement->user->announcements()->whereHas('reports', fn ($q) => $q->where('status', 'open'))->count(),
            'account_age_days' => (int) round($announcement->user->created_at->diffInDays(now())),
            'is_verified_pro' => $announcement->user->is_verified_pro,
        ];

        return response()->json([
            'data' => new AnnouncementResource($announcement),
            'seller_history' => $history,
        ]);
    }

    /**
     * Action de modération : approuver / refuser / demander modification / mettre en attente.
     */
    public function moderate(ModerateRequest $request, Announcement $announcement): JsonResponse
    {
        $action = $request->input('action');
        $reason = $request->input('reason');
        $moderatorId = $request->user()->id;

        match ($action) {
            'approved' => $this->service->approve($announcement, $moderatorId, $reason),
            'rejected' => $this->service->reject($announcement, $reason, $moderatorId),
            'request_changes' => $this->service->requestChanges($announcement, $reason, $moderatorId),
            'on_hold' => $this->service->hold($announcement, $moderatorId, $reason),
        };

        return response()->json([
            'message' => 'Annonce ' . $action . '.',
            'data' => new AnnouncementResource($announcement->fresh([
                'brand', 'model', 'city', 'commune', 'photos',
            ])),
        ]);
    }

    /**
     * Validation en masse pour les comptes pro de confiance.
     */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'exists:announcements,id'],
            'action' => ['required', 'in:approved,rejected'],
        ]);

        $moderatorId = $request->user()->id;

        $count = 0;
        Announcement::whereIn('id', $data['ids'])
            ->where('status', AnnouncementStatus::Pending->value)
            ->get()
            ->each(function (Announcement $announcement) use ($data, $moderatorId, &$count) {
                if ($data['action'] === 'approved') {
                    $this->service->approve($announcement, $moderatorId);
                } else {
                    $this->service->reject($announcement, 'Rejet en masse', $moderatorId);
                }
                $count++;
            });

        return response()->json([
            'message' => "{$count} annonce(s) traitées.",
            'processed' => $count,
        ]);
    }
}