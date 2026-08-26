<?php

namespace Tests\Feature;

use App\Models\SupportInquiry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_ticket_creates_thread_row_and_returns_credentials(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'sup-t1',
            'display_name' => 'S',
            'crypto_assets' => [],
        ]);

        $res = $this->postJson('/api/v1/support/inquiries', [
            'message' => 'Need help with a deposit',
            'client_message_id' => 'cm-1',
        ], ['X-Tenant-Slug' => $tenant->slug]);

        $res->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['inquiry_id', 'request_id', 'subscribe_token']);

        $inquiryId = (string) $res->json('inquiry_id');

        $this->assertDatabaseHas('support_inquiries', [
            'id' => $inquiryId,
            'tenant_id' => $tenant->id,
            'message' => 'Need help with a deposit',
        ]);

        $this->assertDatabaseHas('support_inquiry_messages', [
            'support_inquiry_id' => $inquiryId,
            'body' => 'Need help with a deposit',
            'is_from_admin' => false,
        ]);

        $tok = (string) $res->json('subscribe_token');
        $this->assertNotSame('', trim($tok));
    }

    public function test_follow_up_allows_matching_subscribe_token(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'sup-t2',
            'display_name' => 'S',
            'crypto_assets' => [],
        ]);

        $resp = $this->postJson('/api/v1/support/inquiries', [
            'message' => 'hello',
        ], ['X-Tenant-Slug' => $tenant->slug]);

        $inquiryId = (string) $resp->json('inquiry_id');
        $tok = (string) $resp->json('subscribe_token');

        $f = $this->postJson('/api/v1/support/inquiries/messages', [
            'inquiry_id' => $inquiryId,
            'message' => 'Second line',
            'subscribe_token' => $tok,
        ], ['X-Tenant-Slug' => $tenant->slug]);

        $f->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('support_inquiry_messages', [
            'support_inquiry_id' => $inquiryId,
            'body' => 'Second line',
            'is_from_admin' => false,
        ]);
    }

    public function test_follow_up_denies_when_token_missing_or_wrong(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'sup-t3',
            'display_name' => 'S',
            'crypto_assets' => [],
        ]);

        /** @var SupportInquiry $inquiry */
        $inquiry = SupportInquiry::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'request_id' => '11111111-1111-1111-1111-111111111111',
            'subscribe_token' => 'aaa_valid_token_placeholder_aaa',
            'message' => 'x',
            'email' => null,
            'client_message_id' => null,
            'ip_address' => null,
            'user_agent' => null,
        ]);

        $this->postJson('/api/v1/support/inquiries/messages', [
            'inquiry_id' => $inquiry->getKey(),
            'message' => 'nope',
        ], ['X-Tenant-Slug' => $tenant->slug])
            ->assertStatus(403);

        $this->postJson('/api/v1/support/inquiries/messages', [
            'inquiry_id' => $inquiry->getKey(),
            'message' => 'nope',
            'subscribe_token' => 'wrong',
        ], ['X-Tenant-Slug' => $tenant->slug])
            ->assertStatus(403);
    }

    public function test_owner_follow_up_via_sanctum_without_subscribe_token(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'sup-t4',
            'display_name' => 'S',
            'crypto_assets' => [],
        ]);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        /** @var SupportInquiry $inquiry */
        $inquiry = SupportInquiry::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'request_id' => '22222222-2222-2222-2222-222222222222',
            'subscribe_token' => 'ticket_x',
            'message' => 'hey',
            'email' => null,
            'client_message_id' => null,
            'ip_address' => null,
            'user_agent' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/support/inquiries/messages', [
            'inquiry_id' => $inquiry->getKey(),
            'message' => 'from sanctum owner',
        ], ['X-Tenant-Slug' => $tenant->slug])
            ->assertOk()->assertJsonPath('ok', true);
    }
}
