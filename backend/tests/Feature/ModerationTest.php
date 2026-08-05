<?php

namespace Tests\Feature;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\Brand;
use App\Models\City;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private function setupPendingAnnouncement(User $owner): Announcement
    {
        $brandName = fake()->unique()->word();
        $cityName = fake()->unique()->city();
        $brand = Brand::create(['name' => $brandName, 'slug' => \Illuminate\Support\Str::slug($brandName)]);
        $model = VehicleModel::create(['brand_id' => $brand->id, 'name' => $brandName . ' Modèle', 'slug' => \Illuminate\Support\Str::slug($brandName . '-modele')]);
        $city = City::create(['name' => $cityName, 'slug' => \Illuminate\Support\Str::slug($cityName)]);

        return Announcement::factory()->create([
            'user_id' => $owner->id, 'brand_id' => $brand->id, 'model_id' => $model->id, 'city_id' => $city->id,
        ]);
    }

    public function test_moderation_queue_is_restricted_to_moderators(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/moderation/queue')
            ->assertStatus(403);
    }

    public function test_moderator_sees_pending_queue(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $this->setupPendingAnnouncement($owner);

        $this->actingAs($moderator)
            ->getJson('/api/admin/moderation/queue')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_moderator_can_approve_announcement(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $announcement = $this->setupPendingAnnouncement($owner);

        $this->actingAs($moderator)
            ->postJson("/api/admin/moderation/{$announcement->id}/moderate", ['action' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', AnnouncementStatus::Published->value);

        $this->assertDatabaseHas('announcements', ['id' => $announcement->id, 'status' => AnnouncementStatus::Published->value]);
    }

    public function test_moderator_cannot_reject_without_reason(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $announcement = $this->setupPendingAnnouncement($owner);

        $this->actingAs($moderator)
            ->postJson("/api/admin/moderation/{$announcement->id}/moderate", ['action' => 'rejected'])
            ->assertStatus(422);
    }

    public function test_moderator_can_reject_with_reason_and_trace_recorded(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $announcement = $this->setupPendingAnnouncement($owner);

        $this->actingAs($moderator)
            ->postJson("/api/admin/moderation/{$announcement->id}/moderate", [
                'action' => 'rejected',
                'reason' => 'Prix anormalement bas, veuillez justifier.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', AnnouncementStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'Prix anormalement bas, veuillez justifier.');

        $this->assertDatabaseHas('moderation_actions', [
            'announcement_id' => $announcement->id,
            'moderator_id' => $moderator->id,
            'action' => 'rejected',
        ]);
    }

    public function test_bulk_approval_processes_multiple_announcements(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $a1 = $this->setupPendingAnnouncement($owner);
        $a2 = $this->setupPendingAnnouncement($owner);

        $this->actingAs($moderator)
            ->postJson('/api/admin/moderation/bulk', [
                'ids' => [$a1->id, $a2->id],
                'action' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('processed', 2);

        $this->assertDatabaseHas('announcements', ['id' => $a1->id, 'status' => AnnouncementStatus::Published->value]);
        $this->assertDatabaseHas('announcements', ['id' => $a2->id, 'status' => AnnouncementStatus::Published->value]);
    }

    public function test_rejection_reason_is_visible_to_owner_but_not_to_other_users(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $announcement = $this->setupPendingAnnouncement($owner);

        $this->actingAs($moderator)
            ->postJson("/api/admin/moderation/{$announcement->id}/moderate", [
                'action' => 'rejected',
                'reason' => 'Photos insuffisantes, merci de les compléter.',
            ])
            ->assertOk();

        // Le propriétaire voit le motif dans "mes annonces"
        $this->actingAs($owner)
            ->getJson('/api/my/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.status', AnnouncementStatus::Rejected->value)
            ->assertJsonPath('data.0.rejection_reason', 'Photos insuffisantes, merci de les compléter.');

        // Un autre utilisateur (non propriétaire, non modérateur) ne le voit pas
        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->getJson("/api/announcements/{$announcement->slug}")
            ->assertNotFound();
    }
}