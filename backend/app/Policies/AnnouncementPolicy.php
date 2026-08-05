<?php

namespace App\Policies;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function update(User $user, Announcement $announcement): bool
    {
        return $user->id === $announcement->user_id
            || $user->isModerator();
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->id === $announcement->user_id
            || $user->isModerator();
    }

    public function viewAny(User $user): bool
    {
        return $user->isModerator();
    }

    public function moderate(User $user): bool
    {
        return $user->isModerator();
    }
}