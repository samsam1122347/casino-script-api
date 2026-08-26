<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SupportInquiries\SupportInquiryResource;
use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use App\Models\CrashRound;
use App\Models\SupportInquiry;
use App\Models\User;
use App\Models\WalletTransaction;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'CrashX overview';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $deposit24hMinor = (int) WalletTransaction::query()
            ->where('type', 'deposit')
            ->where('created_at', '>=', now()->subDay())
            ->sum('amount_minor');

        $withdraw24hMinor = (int) WalletTransaction::query()
            ->where('type', 'withdrawal')
            ->where('created_at', '>=', now()->subDay())
            ->sum('amount_minor');

        $support7d = SupportInquiry::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $rounds7d = CrashRound::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make(__('Players'), User::query()->count())
                ->icon(Heroicon::OutlinedUserGroup),
            Stat::make(__('Deposits 24h'), '$'.number_format($deposit24hMinor / 100, 2))
                ->description(__('Ledger: deposits'))
                ->url(WalletTransactionResource::getUrl('index', [
                    'tableFilters' => ['type' => ['value' => 'deposit']],
                ])),
            Stat::make(__('Withdrawals 24h'), '$'.number_format(abs($withdraw24hMinor) / 100, 2))
                ->description(__('Ledger: withdrawals'))
                ->url(WalletTransactionResource::getUrl('index', [
                    'tableFilters' => ['type' => ['value' => 'withdrawal']],
                ])),
            Stat::make(__('Support 7d'), (string) $support7d)
                ->icon(Heroicon::OutlinedChatBubbleLeft)
                ->url(SupportInquiryResource::getUrl('index')),
            Stat::make(__('Crash rounds 7d'), (string) $rounds7d)
                ->icon(Heroicon::OutlinedBolt),
        ];
    }
}
