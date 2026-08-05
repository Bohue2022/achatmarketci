<?php

namespace App\Services;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Str;

class AnnouncementService
{
    /**
     * Construit le slug SEO unique type marque-modele-ville.
     */
    public function buildSlug(Announcement $announcement): string
    {
        $base = Str::slug(
            trim("{$announcement->brand->name} {$announcement->model->name} {$announcement->city->name} {$announcement->year}")
        );

        $slug = $base;
        $i = 2;
        while (Announcement::where('slug', $slug)->where('id', '!=', $announcement->id)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    /**
     * Vérifie que l'utilisateur peut encore publier une nouvelle annonce active.
     */
    public function assertCanPublish(User $user): void
    {
        $limit = $user->activeAnnouncementLimit();

        // null = illimité (pro avec plan dédié, ou certains rôles)
        if ($limit === null) {
            return;
        }

        $activeCount = $user->announcements()
            ->whereIn('status', [AnnouncementStatus::Published->value, AnnouncementStatus::Pending->value])
            ->count();

        if ($activeCount >= $limit) {
            throw new \App\Exceptions\QuotaExceededException(
                "Vous avez atteint votre limite de {$limit} annonce(s) active(s). Passez à un plan professionnel pour publier sans limite."
            );
        }
    }

    /**
     * Calcule la date d'expiration selon la durée de l'abonnement.
     */
    public function computeExpiry(User $user): \Illuminate\Support\Carbon
    {
        return now()->addDays($user->listingDurationDays());
    }

    /**
     * Prépare une annonce pour mise en modération (status pending + expiration calculée).
     */
    public function submitForReview(Announcement $announcement): Announcement
    {
        $announcement->status = AnnouncementStatus::Pending->value;
        $announcement->expires_at = $this->computeExpiry($announcement->user);
        $announcement->save();

        return $announcement->fresh();
    }

    /**
     * Passe une annonce en publique.
     */
    public function approve(Announcement $announcement, ?int $moderatorId = null, ?string $reason = null): Announcement
    {
        $announcement->status = AnnouncementStatus::Published->value;
        $announcement->moderated_at = now();
        $announcement->published_at = $announcement->published_at ?? now();
        $announcement->rejection_reason = null;
        $announcement->save();

        if ($moderatorId) {
            $announcement->moderationActions()->create([
                'moderator_id' => $moderatorId,
                'action' => 'approved',
                'reason' => $reason,
            ]);
        }

        return $announcement->fresh();
    }

    /**
     * Refuse une annonce (motif obligatoire).
     */
    public function reject(Announcement $announcement, string $reason, int $moderatorId): Announcement
    {
        $announcement->status = AnnouncementStatus::Rejected->value;
        $announcement->moderated_at = now();
        $announcement->rejection_reason = $reason;
        $announcement->save();

        $announcement->moderationActions()->create([
            'moderator_id' => $moderatorId,
            'action' => 'rejected',
            'reason' => $reason,
        ]);

        return $announcement->fresh();
    }

    /**
     * Demande une modification au vendeur (retourne en brouillon).
     */
    public function requestChanges(Announcement $announcement, string $reason, int $moderatorId): Announcement
    {
        $announcement->status = AnnouncementStatus::Draft->value;
        $announcement->moderated_at = now();
        $announcement->rejection_reason = $reason;
        $announcement->save();

        $announcement->moderationActions()->create([
            'moderator_id' => $moderatorId,
            'action' => 'request_changes',
            'reason' => $reason,
        ]);

        return $announcement->fresh();
    }

    /**
     * Met une annonce en attente sans décision finale.
     */
    public function hold(Announcement $announcement, int $moderatorId, ?string $reason = null): Announcement
    {
        $announcement->moderated_at = now();
        $announcement->save();

        $announcement->moderationActions()->create([
            'moderator_id' => $moderatorId,
            'action' => 'on_hold',
            'reason' => $reason,
        ]);

        return $announcement->fresh();
    }
}