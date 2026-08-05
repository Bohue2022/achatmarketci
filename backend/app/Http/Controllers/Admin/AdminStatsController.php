<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ModerationAction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AdminStatsController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $pending = AnnouncementStatus::Pending->value;
        $published = AnnouncementStatus::Published->value;
        $rejected = AnnouncementStatus::Rejected->value;

        return response()->json([
            'data' => [
                'listings' => [
                    'active' => Announcement::active()->count(),
                    'pending' => Announcement::where('status', $pending)->count(),
                    'published_total' => Announcement::where('status', $published)->count(),
                    'rejected_total' => Announcement::where('status', $rejected)->count(),
                    'expired' => Announcement::where('status', AnnouncementStatus::Expired->value)->count(),
                    'suspended' => Announcement::where('status', AnnouncementStatus::Suspended->value)->count(),
                ],
                'moderation' => [
                    'pending' => Announcement::where('status', $pending)->count(),
                    'total_actions' => ModerationAction::count(),
                    'avg_moderation_minutes' => $this->avgModerationMinutes(),
                    'approval_rate' => $this->ratio(
                        ModerationAction::where('action', 'approved')->count(),
                        ModerationAction::count()
                    ),
                    'by_moderator' => $this->moderatorBreakdown(),
                ],
                'users' => [
                    'total' => User::count(),
                    'particuliers' => User::where('role', 'user')->count(),
                    'pros' => User::where('role', 'pro')->count(),
                    'verified_pros' => User::where('role', 'pro')->where('is_verified_pro', true)->count(),
                    'banned' => User::whereNotNull('banned_at')->count(),
                    'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                ],
                'daily' => $this->dailySeries(14),
                'recents' => [
                    'announcements' => $this->recentAnnouncements(),
                    'moderation_actions' => $this->recentModerationActions(),
                    'users' => $this->recentUsers(),
                ],
            ],
        ]);
    }

    /** Moyenne du temps de modération (calculée en PHP, agnostique au moteur de BDD). */
    private function avgModerationMinutes(): ?float
    {
        $durations = Announcement::whereNotNull('moderated_at')
            ->get(['created_at', 'moderated_at'])
            ->filter(fn ($a) => $a->moderated_at->greaterThan($a->created_at))
            ->map(fn ($a) => $a->moderated_at->diffInSeconds($a->created_at))
            ->values();

        return $durations->isEmpty() ? null : round($durations->avg() / 60, 1);
    }

    /** Actions par modérateur (back-office). */
    private function moderatorBreakdown(): array
    {
        return ModerationAction::with('moderator')
            ->get()
            ->groupBy(fn ($m) => $m->moderator?->name ?? 'Ancien modérateur')
            ->map(function ($group) {
                return [
                    'moderator' => $group->first()->moderator?->name ?? 'Ancien modérateur',
                    'approved' => $group->where('action', 'approved')->count(),
                    'rejected' => $group->where('action', 'rejected')->count(),
                    'total' => $group->count(),
                ];
            })
            ->values()
            ->all();
    }

    /** Série quotidienne (annonces créées / inscrits) sur N jours pour les graphiques. */
    private function dailySeries(int $days): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $announcementsDaily = Announcement::where('created_at', '>=', $since)
            ->get(['created_at'])
            ->groupBy(fn ($a) => $a->created_at->format('Y-m-d'))
            ->map->count();

        $usersDaily = User::where('created_at', '>=', $since)
            ->get(['created_at'])
            ->groupBy(fn ($u) => $u->created_at->format('Y-m-d'))
            ->map->count();

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $key = $day->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'label' => $day->format('d/m'),
                'announcements' => $announcementsDaily[$key] ?? 0,
                'users' => $usersDaily[$key] ?? 0,
            ];
        }

        return $series;
    }

    private function recentAnnouncements(): array
    {
        return Announcement::with(['user', 'brand', 'model'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'slug' => $a->slug,
                'title' => $a->title,
                'status' => $a->status,
                'price_formatted' => $a->price_formatted,
                'seller_name' => $a->user?->name,
                'seller_role' => $a->user?->role?->value,
                'created_at' => $a->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function recentModerationActions(): array
    {
        return ModerationAction::with(['moderator', 'announcement'])
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (ModerationAction $m) => [
                'id' => $m->id,
                'action' => $m->action,
                'reason' => $m->reason,
                'announcement_title' => $m->announcement?->title,
                'moderator_name' => $m->moderator?->name,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function recentUsers(): array
    {
        return User::latest()
            ->take(5)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'is_verified_pro' => (bool) $u->is_verified_pro,
                'is_banned' => (bool) $u->banned_at,
                'created_at' => $u->created_at?->toIso8601String(),
            ])
            ->all();
    }

    protected function ratio(int $part, int $total): ?float
    {
        return $total > 0 ? round($part / $total * 100, 1) : null;
    }
}