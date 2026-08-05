<?php

namespace Tests\Feature;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\Brand;
use App\Models\City;
use App\Models\Conversation;
use App\Models\User;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private function publishedAnnouncement(User $seller): Announcement
    {
        $brand = Brand::create(['name' => fake()->unique()->word(), 'slug' => Str::slug(fake()->unique()->word())]);
        $model = VehicleModel::create(['brand_id' => $brand->id, 'name' => $brand->name . ' M', 'slug' => Str::slug($brand->name . '-m')]);
        $city = City::create(['name' => fake()->unique()->city(), 'slug' => Str::slug(fake()->unique()->city())]);

        return Announcement::factory()->published()->create([
            'user_id' => $seller->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'city_id' => $city->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_conversations(): void
    {
        $this->getJson('/api/conversations')->assertStatus(401);
    }

    public function test_buyer_can_start_conversation_on_published_announcement(): void
    {
        $seller = User::factory()->pro()->create();
        $announcement = $this->publishedAnnouncement($seller);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)
            ->postJson("/api/announcements/{$announcement->slug}/messages", ['message' => 'Bonjour, le véhicule est-il disponible ?'])
            ->assertStatus(201)
            ->assertJsonPath('data.other_party.id', $seller->id)
            ->assertJsonPath('data.messages.0.body', 'Bonjour, le véhicule est-il disponible ?');

        $this->assertDatabaseHas('conversations', ['announcement_id' => $announcement->id, 'buyer_id' => $buyer->id]);
        $this->assertDatabaseHas('messages', ['body' => 'Bonjour, le véhicule est-il disponible ?', 'sender_id' => $buyer->id]);
    }

    public function test_buyer_cannot_message_own_announcement(): void
    {
        $seller = User::factory()->create();
        $announcement = $this->publishedAnnouncement($seller);

        $this->actingAs($seller)
            ->postJson("/api/announcements/{$announcement->slug}/messages", ['message' => 'Salut'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'self_conversation');
    }

    public function test_buyer_cannot_start_conversation_on_non_published_announcement(): void
    {
        $seller = User::factory()->create();
        $announcement = $this->publishedAnnouncement($seller);
        $announcement->update(['status' => AnnouncementStatus::Pending->value]);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)
            ->postJson("/api/announcements/{$announcement->slug}/messages", ['message' => 'Salut'])
            ->assertStatus(404);
    }

    public function test_only_participants_can_view_conversation(): void
    {
        $seller = User::factory()->pro()->create();
        $announcement = $this->publishedAnnouncement($seller);
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();

        $conversation = Conversation::create(['announcement_id' => $announcement->id, 'buyer_id' => $buyer->id, 'last_message_at' => now()]);

        $this->actingAs($stranger)
            ->getJson("/api/conversations/{$conversation->id}")
            ->assertStatus(403);

        $this->actingAs($buyer)
            ->getJson("/api/conversations/{$conversation->id}")
            ->assertOk();
    }

    public function test_seller_sees_unread_and_view_marks_messages_as_read(): void
    {
        $seller = User::factory()->pro()->create();
        $announcement = $this->publishedAnnouncement($seller);
        $buyer = User::factory()->create();

        $conversation = Conversation::create(['announcement_id' => $announcement->id, 'buyer_id' => $buyer->id, 'last_message_at' => now()]);
        $conversation->messages()->create(['sender_id' => $buyer->id, 'body' => 'Vendu ?']);

        // Le vendeur voit 1 non lu
        $this->actingAs($seller)
            ->getJson('/api/conversations/unread-count')
            ->assertOk()
            ->assertJsonPath('unread', 1);

        $this->actingAs($seller)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.unread_count', 1);

        // Le vendeur ouvre le fil -> lecture
        $this->actingAs($seller)
            ->getJson("/api/conversations/{$conversation->id}")
            ->assertOk();

        $this->assertDatabaseHas('messages', ['id' => 1, 'read_at' => now()]);
        $this->actingAs($seller)
            ->getJson('/api/conversations/unread-count')
            ->assertJsonPath('unread', 0);
    }

    public function test_only_participant_can_send_message(): void
    {
        $seller = User::factory()->pro()->create();
        $announcement = $this->publishedAnnouncement($seller);
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();

        $conversation = Conversation::create(['announcement_id' => $announcement->id, 'buyer_id' => $buyer->id, 'last_message_at' => now()]);

        $this->actingAs($stranger)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['message' => 'Bonjour'])
            ->assertStatus(403);

        $this->actingAs($seller)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['message' => 'Oui, toujours disponible.'])
            ->assertStatus(201);

        $this->assertDatabaseHas('messages', ['body' => 'Oui, toujours disponible.', 'sender_id' => $seller->id]);
    }

    public function test_message_body_is_required(): void
    {
        $seller = User::factory()->pro()->create();
        $announcement = $this->publishedAnnouncement($seller);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)
            ->postJson("/api/announcements/{$announcement->slug}/messages", ['message' => ''])
            ->assertStatus(422);
    }
}