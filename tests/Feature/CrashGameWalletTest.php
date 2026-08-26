<?php

namespace Tests\Feature;

use App\Models\CrashBet;
use App\Models\CrashRound;
use App\Models\CrashTenantSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Crash\Engine\CrashRoundEngine;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrashGameWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_place_bet_idempotency_returns_original_bet_row(): void
    {
        $this->seed(TenantSeeder::class);
        config(['crash.engine.enabled' => true]);

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();
        /** @var CrashTenantSettings $st */
        $st = CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
        $st->forceFill([
            'engine_enabled' => true,
            'game_paused' => false,
            'game_enabled' => true,
            'betting_duration_seconds' => 10,
            'provably_fair_enabled' => false,
            'growth_per_second' => 5.5,
            'tick_hz' => 20,
            'pending_operator_crash_multiplier' => 1.06,
            'house_edge_bp' => 400,
        ])->save();

        app(CrashRoundEngine::class)->tickTenant($tenant);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 10_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $idem = 'place-test-key';

        $r1 = $this->postJson('/api/v1/games/crash/bets', [
            'stake_minor' => 500,
        ], [
            'X-Tenant-Slug' => $tenant->slug,
            'Idempotency-Key' => $idem,
        ]);

        $r2 = $this->postJson('/api/v1/games/crash/bets', [
            'stake_minor' => 500,
        ], [
            'X-Tenant-Slug' => $tenant->slug,
            'Idempotency-Key' => $idem,
        ]);

        $r1->assertOk();
        $r2->assertOk();

        $id1 = (string) ($r1->json('bet.id'));
        $id2 = (string) ($r2->json('bet.id'));
        $this->assertSame($id1, $id2);
        $this->assertSame(1, CrashBet::query()->count());
    }

    public function test_cannot_reuse_place_idempotency_key_with_new_stake(): void
    {
        $this->seed(TenantSeeder::class);
        config(['crash.engine.enabled' => true]);

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();
        /** @var CrashTenantSettings $st */
        $st = CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
        $st->forceFill([
            'engine_enabled' => true,
            'game_paused' => false,
            'game_enabled' => true,
            'betting_duration_seconds' => 60,
            'provably_fair_enabled' => false,
        ])->save();

        app(CrashRoundEngine::class)->tickTenant($tenant);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 10_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $idem = 'reuse-key';

        $this->postJson('/api/v1/games/crash/bets', [
            'stake_minor' => 500,
        ], [
            'X-Tenant-Slug' => $tenant->slug,
            'Idempotency-Key' => $idem,
        ])->assertOk();

        $this->postJson('/api/v1/games/crash/bets', [
            'stake_minor' => 600,
        ], [
            'X-Tenant-Slug' => $tenant->slug,
            'Idempotency-Key' => $idem,
        ])->assertStatus(409);
    }

    public function test_player_can_cancel_queued_bet_during_betting_window(): void
    {
        $this->seed(TenantSeeder::class);
        config(['crash.engine.enabled' => true]);

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();
        /** @var CrashTenantSettings $st */
        $st = CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
        $st->forceFill([
            'engine_enabled' => true,
            'game_paused' => false,
            'game_enabled' => true,
            'betting_duration_seconds' => 60,
            'provably_fair_enabled' => false,
        ])->save();

        app(CrashRoundEngine::class)->tickTenant($tenant);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 10_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/games/crash/bets', [
            'stake_minor' => 500,
        ], [
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk();

        $this->assertSame(9_500, (int) $wallet->fresh()->balance_minor);

        $this->postJson('/api/v1/games/crash/bets/cancel', [], [
            'X-Tenant-Slug' => $tenant->slug,
        ])
            ->assertOk()
            ->assertJsonPath('balance_after_minor', 10_000)
            ->assertJsonPath('bet.status', 'refunded');

        $this->assertSame(10_000, (int) $wallet->fresh()->balance_minor);
        $this->assertSame('refunded', CrashBet::query()->firstOrFail()->status);
    }

    public function test_player_can_place_again_after_cancelling_same_round(): void
    {
        $this->seed(TenantSeeder::class);
        config(['crash.engine.enabled' => true]);

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();
        /** @var CrashTenantSettings $st */
        $st = CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
        $st->forceFill([
            'engine_enabled' => true,
            'game_paused' => false,
            'game_enabled' => true,
            'betting_duration_seconds' => 60,
            'provably_fair_enabled' => false,
        ])->save();

        app(CrashRoundEngine::class)->tickTenant($tenant);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 10_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/games/crash/bets', [
            'stake_minor' => 500,
        ], [
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk();

        $this->postJson('/api/v1/games/crash/bets/cancel', [], [
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk();

        $this->postJson('/api/v1/games/crash/bets', [
            'stake_minor' => 700,
            'auto_cashout_multiplier' => 2,
        ], [
            'X-Tenant-Slug' => $tenant->slug,
        ])
            ->assertOk()
            ->assertJsonPath('balance_after_minor', 9_300)
            ->assertJsonPath('bet.stake_minor', 700)
            ->assertJsonPath('bet.status', 'open');

        $this->assertSame(9_300, (int) $wallet->fresh()->balance_minor);
        $this->assertSame(1, CrashBet::query()->count());
        $this->assertSame('open', CrashBet::query()->firstOrFail()->status);
    }

    public function test_my_bets_returns_open_bet_for_running_round_after_refresh(): void
    {
        $this->seed(TenantSeeder::class);
        config(['crash.engine.enabled' => true]);

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();
        /** @var CrashTenantSettings $st */
        $st = CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
        $st->forceFill([
            'engine_enabled' => true,
            'game_paused' => false,
            'game_enabled' => true,
            'betting_duration_seconds' => 60,
            'provably_fair_enabled' => false,
        ])->save();

        app(CrashRoundEngine::class)->tickTenant($tenant);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 10_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/games/crash/bets', [
            'stake_minor' => 500,
            'auto_cashout_multiplier' => 2,
        ], [
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk();

        /** @var CrashRound $round */
        $round = CrashRound::query()->where('tenant_id', $tenant->getKey())->latest('created_at')->firstOrFail();
        $round->forceFill([
            'phase' => 'running',
            'betting_closes_at' => now()->subSecond(),
            'started_running_at' => now()->subSecond(),
        ])->save();

        $this->getJson('/api/v1/games/crash/my-bets?limit=10', [
            'X-Tenant-Slug' => $tenant->slug,
        ])
            ->assertOk()
            ->assertJsonPath('bets.0.crash_round_id', (string) $round->id)
            ->assertJsonPath('bets.0.stake_minor', 500)
            ->assertJsonPath('bets.0.auto_cashout_multiplier', '2.0000')
            ->assertJsonPath('bets.0.status', 'open');

        $this->getJson('/api/v1/games/crash/state', [
            'X-Tenant-Slug' => $tenant->slug,
        ])
            ->assertOk()
            ->assertJsonPath('round.id', (string) $round->id)
            ->assertJsonPath('round.phase', 'running');
    }

    public function test_live_bets_returns_sanitized_tenant_scoped_rows(): void
    {
        $this->seed(TenantSeeder::class);

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();

        /** @var CrashRound $round */
        $round = CrashRound::query()->create([
            'tenant_id' => $tenant->getKey(),
            'external_round_id' => 'live-bets-test',
            'phase' => 'running',
            'started_at' => now()->subMinute(),
            'started_running_at' => now()->subSeconds(10),
        ]);

        /** @var User $winner */
        $winner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username' => 'winner_player',
            'email' => 'winner@example.test',
        ]);
        /** @var User $loser */
        $loser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username' => 'loser_player',
            'email' => 'loser@example.test',
        ]);

        $lostBet = CrashBet::query()->create([
            'crash_round_id' => $round->id,
            'user_id' => $loser->id,
            'stake_minor' => 700,
            'status' => 'lost',
        ]);

        $winBet = CrashBet::query()->create([
            'crash_round_id' => $round->id,
            'user_id' => $winner->id,
            'stake_minor' => 500,
            'cashout_multiplier' => 2.25,
            'payout_minor' => 1125,
            'status' => 'cashed_out',
        ]);

        Sanctum::actingAs($winner, ['*']);

        $response = $this->getJson('/api/v1/games/crash/live-bets?limit=10', [
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk()
            ->assertJsonMissing(['player' => 'winner_player'])
            ->assertJsonMissing(['email' => 'winner@example.test']);

        /** @var array<int, array<string, mixed>> $bets */
        $bets = $response->json('bets');
        $winnerRow = collect($bets)->firstWhere('id', (string) $winBet->id);
        $loserRow = collect($bets)->firstWhere('id', (string) $lostBet->id);

        $this->assertSame((string) $round->id, $winnerRow['crash_round_id'] ?? null);
        $this->assertSame(500, $winnerRow['stake_minor'] ?? null);
        $this->assertSame('cashed_out', $winnerRow['status'] ?? null);
        $this->assertSame(2.25, $winnerRow['cashout_multiplier'] ?? null);
        $this->assertSame(1125, $winnerRow['payout_minor'] ?? null);
        $this->assertSame('real', $winnerRow['source'] ?? null);
        $this->assertSame('lost', $loserRow['status'] ?? null);
        $this->assertSame(0, $loserRow['payout_minor'] ?? null);
    }

    public function test_engine_auto_cashout_pays_open_bet_when_target_is_reached(): void
    {
        $this->seed(TenantSeeder::class);
        config(['crash.engine.enabled' => true]);

        $tenant = Tenant::query()->where('slug', 'crashx')->firstOrFail();
        /** @var CrashTenantSettings $st */
        $st = CrashTenantSettings::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
        $st->forceFill([
            'engine_enabled' => true,
            'game_paused' => false,
            'game_enabled' => true,
            'betting_duration_seconds' => 60,
            'provably_fair_enabled' => false,
            'growth_per_second' => 1.0,
            'pending_operator_crash_multiplier' => 10.0,
        ])->save();

        app(CrashRoundEngine::class)->tickTenant($tenant);

        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'currency' => 'USD',
            'balance_minor' => 10_000,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/games/crash/bets', [
            'stake_minor' => 1_000,
            'auto_cashout_multiplier' => 2.0,
        ], [
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk();

        /** @var CrashRound $round */
        $round = CrashRound::query()->where('tenant_id', $tenant->getKey())->latest('created_at')->firstOrFail();
        $round->forceFill([
            'betting_closes_at' => now()->subSecond(),
        ])->save();

        app(CrashRoundEngine::class)->tickTenant($tenant);

        $round->refresh()->forceFill([
            'started_running_at' => now()->subSeconds(2),
        ])->save();

        app(CrashRoundEngine::class)->tickTenant($tenant);

        $bet = CrashBet::query()->firstOrFail();
        $this->assertSame('cashed_out', $bet->status);
        $this->assertSame(2.0, (float) $bet->cashout_multiplier);
        $this->assertSame(2_000, (int) $bet->payout_minor);
        $this->assertSame(11_000, (int) $wallet->fresh()->balance_minor);
    }
}
