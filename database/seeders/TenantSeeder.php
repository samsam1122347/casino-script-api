<?php

namespace Database\Seeders;

use App\Models\CrashTenantSettings;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /** Default tenant rows + Crash settings. Does not create users, wallets, or practice balances. */
    public function run(): void
    {
        $slug = (string) config('gaming.default_tenant_slug', 'crashx');

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'display_name' => 'CrashX',
                'tagline' => 'Crypto Crash — provably fair, instant payouts.',
                'theme' => [
                    '--color-brand' => '#00f080',
                    '--color-purple' => '#8a55ff',
                    '--color-gold' => '#d4af37',
                    '--color-orange' => '#ff7a32',
                    '--color-pink' => '#ff3368',
                ],
                'crypto_assets' => self::depositWallets(),
            ],
        );

        // Create the settings row with product defaults on first seed. firstOrCreate
        // (not updateOrCreate) so re-seeding never clobbers operator tuning — house
        // edge, limits, growth, tick rate, etc.
        /** @var CrashTenantSettings $settings */
        $settings = CrashTenantSettings::query()->firstOrCreate(
            ['tenant_id' => $tenant->getKey()],
            CrashTenantSettings::defaultsForTenant((string) $tenant->getKey()),
        );

        // Re-seeding always restores run-state flags and syncs the pace constants to
        // the current product defaults. Growth/betting/tick can still be overridden in
        // Filament afterwards — but every fresh deploy starts from a known good pace.
        $settings->forceFill([
            'engine_enabled' => true,
            'game_enabled' => true,
            'game_paused' => false,
            'growth_per_second' => (float) config('crash.defaults.growth_per_second', 0.055),
            'betting_duration_seconds' => (int) config('crash.defaults.betting_duration_seconds', 10),
            'tick_hz' => max(1, (int) config('crash.defaults.tick_hz', 10)),
        ])->save();
    }

    /** @return list<array<string, mixed>> */
    public static function depositWallets(): array
    {
        return [
            ['id' => 'btc', 'symbol' => 'BTC', 'name' => 'Bitcoin', 'network' => 'BTC', 'address' => 'bc1qw9cds585jpch9yjxpmfpdfavgvm4lq9d63xs9x', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 25],
            ['id' => 'usdt_bep20', 'symbol' => 'USDT', 'name' => 'Tether', 'network' => 'BEP20', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 10],
            ['id' => 'usdt_eth', 'symbol' => 'USDT', 'name' => 'Tether', 'network' => 'ERC20', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 10],
            ['id' => 'eth', 'symbol' => 'ETH', 'name' => 'Ethereum', 'network' => 'ERC20', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 20],
            ['id' => 'trx', 'symbol' => 'TRX', 'name' => 'Tron', 'network' => 'TRC20', 'address' => 'TG7p7GsVtDCxY9FMSmnbjh4hgLLLQFrYam', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 10],
            ['id' => 'sol', 'symbol' => 'SOL', 'name' => 'Solana', 'network' => 'SOL', 'address' => 'AjnJAPkfHtyBcxTLhsK6bLGd1kyvCEtdnV48fXwCtWUY', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 10],
            ['id' => 'ton', 'symbol' => 'TON', 'name' => 'Toncoin', 'network' => 'TON', 'address' => 'UQC5J9d2ZaJMYWjk2-dCdPk8R7r86QyKwwyFq-EVz6m_P2Ai', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 10],
            ['id' => 'xrp', 'symbol' => 'XRP', 'name' => 'Ripple', 'network' => 'XRP', 'address' => 'rHvjg6kFHESqZonUyLvQu6K6s2YGvFNrHM', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 10],
            ['id' => 'bnb', 'symbol' => 'BNB', 'name' => 'Binance Coin', 'network' => 'BEP20', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 10],
            ['id' => 'usdc_eth', 'symbol' => 'USDC', 'name' => 'USD Coin', 'network' => 'ERC20', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 10],
            ['id' => 'usdt_tron', 'symbol' => 'USDT', 'name' => 'Tether', 'network' => 'TRC20', 'address' => 'TG7p7GsVtDCxY9FMSmnbjh4hgLLLQFrYam', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 10],
            ['id' => 'usdt_polygon', 'symbol' => 'USDT', 'name' => 'Tether', 'network' => 'Polygon', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA', 'min_deposit_usd' => 50, 'min_withdraw_usd' => 10],
        ];
    }
}
