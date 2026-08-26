<?php

namespace Tests\Feature;

use Database\Seeders\TenantSeeder;
use Tests\TestCase;

class TenantDepositWalletsTest extends TestCase
{
    public function test_default_deposit_wallets_are_exactly_the_configured_wallets(): void
    {
        $this->assertSame([
            ['id' => 'btc', 'symbol' => 'BTC', 'network' => 'BTC', 'address' => 'bc1qw9cds585jpch9yjxpmfpdfavgvm4lq9d63xs9x'],
            ['id' => 'usdt_bep20', 'symbol' => 'USDT', 'network' => 'BEP20', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA'],
            ['id' => 'usdt_eth', 'symbol' => 'USDT', 'network' => 'ERC20', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA'],
            ['id' => 'eth', 'symbol' => 'ETH', 'network' => 'ERC20', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA'],
            ['id' => 'trx', 'symbol' => 'TRX', 'network' => 'TRC20', 'address' => 'TG7p7GsVtDCxY9FMSmnbjh4hgLLLQFrYam'],
            ['id' => 'sol', 'symbol' => 'SOL', 'network' => 'SOL', 'address' => 'AjnJAPkfHtyBcxTLhsK6bLGd1kyvCEtdnV48fXwCtWUY'],
            ['id' => 'ton', 'symbol' => 'TON', 'network' => 'TON', 'address' => 'UQC5J9d2ZaJMYWjk2-dCdPk8R7r86QyKwwyFq-EVz6m_P2Ai'],
            ['id' => 'xrp', 'symbol' => 'XRP', 'network' => 'XRP', 'address' => 'rHvjg6kFHESqZonUyLvQu6K6s2YGvFNrHM'],
            ['id' => 'bnb', 'symbol' => 'BNB', 'network' => 'BEP20', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA'],
            ['id' => 'usdc_eth', 'symbol' => 'USDC', 'network' => 'ERC20', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA'],
            ['id' => 'usdt_tron', 'symbol' => 'USDT', 'network' => 'TRC20', 'address' => 'TG7p7GsVtDCxY9FMSmnbjh4hgLLLQFrYam'],
            ['id' => 'usdt_polygon', 'symbol' => 'USDT', 'network' => 'Polygon', 'address' => '0x7CaE964bA33D9e1037bedF929b6a29be9B50AaAA'],
        ], array_map(
            fn (array $wallet): array => [
                'id' => (string) $wallet['id'],
                'symbol' => (string) $wallet['symbol'],
                'network' => (string) $wallet['network'],
                'address' => (string) $wallet['address'],
            ],
            TenantSeeder::depositWallets(),
        ));
    }
}
