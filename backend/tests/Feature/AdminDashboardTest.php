<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderator_can_read_dashboard_stats(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'listings' => ['active', 'pending', 'published_total', 'rejected_total'],
                    'moderation' => ['pending', 'total_actions', 'approval_rate', 'by_moderator'],
                    'users' => ['total', 'particuliers', 'pros', 'verified_pros', 'banned', 'new_this_month'],
                    'daily' => [['date', 'announcements', 'users']],
                    'recents' => ['announcements', 'moderation_actions', 'users'],
                ],
            ]);
    }

    public function test_admin_can_read_announcements_and_users_lists(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/announcements?status=pending')
            ->assertOk()
            ->assertJsonStructure(['data', 'counts', 'meta' => ['total', 'last_page']]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?per_page=5')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'last_page']]);
    }

    public function test_admin_announcements_counts_keyed_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/announcements')
            ->assertOk()
            ->assertJsonPath('counts.pending', 0)
            ->assertJsonPath('counts.published', 0);
    }

    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertStatus(403);
    }

    public function test_admin_can_create_a_dedicated_moderator_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/moderators', [
                'name' => 'Moussa Diarra',
                'email' => 'moussa.modo@rr.ci',
                'phone' => '+2250700000099',
                'password' => 'SecretPass1!',
                'password_confirmation' => 'SecretPass1!',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'moderator')
            ->assertJsonPath('data.email', 'moussa.modo@rr.ci');

        $moderator = User::where('email', 'moussa.modo@rr.ci')->first();
        $this->assertNotNull($moderator);
        $this->assertTrue($moderator->isModerator());
        $this->assertFalse($moderator->isAdmin());
    }

    public function test_moderator_can_log_in_with_created_credentials(): void
    {
        User::factory()->moderator()->create(['email' => 'modo2@rr.ci', 'password' => 'secretpass']);

        $this->postJson('/api/auth/login', ['email' => 'modo2@rr.ci', 'password' => 'secretpass'])
            ->assertOk()
            ->assertJsonPath('user.role', 'moderator');
    }

    public function test_admin_can_list_and_remove_a_moderator_account(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create(['email' => 'modo3@rr.ci']);
        $announcement = \App\Models\Announcement::factory()->create();
        \App\Models\ModerationAction::create([
            'announcement_id' => $announcement->id,
            'moderator_id' => $moderator->id,
            'action' => 'approved',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/moderators')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/moderators/{$moderator->id}")
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $moderator->id]);
        $this->assertTrue($moderator->fresh()->trashed());

        // L'historique de modÃ©ration est conservÃ©.
        $this->assertDatabaseHas('moderation_actions', ['moderator_id' => $moderator->id]);
    }

    public function test_removed_moderator_can_no_longer_log_in(): void
    {
        $moderator = User::factory()->moderator()->create(['email' => 'modo4@rr.ci', 'password' => 'secretpass']);
        $moderator->delete();

        $this->postJson('/api/auth/login', ['email' => 'modo4@rr.ci', 'password' => 'secretpass'])
            ->assertStatus(401);
    }

    public function test_moderator_cannot_manage_moderator_accounts(): void
    {
        $moderator = User::factory()->moderator()->create();
        $target = User::factory()->moderator()->create();

        $this->actingAs($moderator, 'sanctum')
            ->getJson('/api/admin/moderators')
            ->assertStatus(403);

        $this->actingAs($moderator, 'sanctum')
            ->postJson('/api/admin/moderators', [
                'name' => 'Tentative',
                'email' => 'tentative@rr.ci',
                'password' => 'SecretPass1!',
                'password_confirmation' => 'SecretPass1!',
            ])
            ->assertStatus(403);

        $this->actingAs($moderator, 'sanctum')
            ->deleteJson("/api/admin/moderators/{$target->id}")
            ->assertStatus(403);

        $this->assertFalse($target->fresh()->trashed());
    }

    public function test_cannot_create_duplicate_moderator_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->moderator()->create(['email' => 'existe@rr.ci']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/moderators', [
                'name' => 'Doublon',
                'email' => 'existe@rr.ci',
                'password' => 'SecretPass1!',
                'password_confirmation' => 'SecretPass1!',
            ])
            ->assertStatus(422);
    }
}