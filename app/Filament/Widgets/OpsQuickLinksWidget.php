<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CrashOperationsConsole;
use App\Filament\Resources\SupportInquiries\SupportInquiryResource;
use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use Filament\Widgets\Widget;

class OpsQuickLinksWidget extends Widget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.ops-quick-links';

    /** @return list<array{label: string, url: string, description: string}> */
    public function getLinks(): array
    {
        return [
            [
                'label' => __('Crash live ops'),
                'url' => CrashOperationsConsole::getUrl(),
                'description' => __('Realtime round monitor'),
            ],
            [
                'label' => __('Ledger'),
                'url' => WalletTransactionResource::getUrl('index'),
                'description' => __('Deposits, withdrawals, bonuses'),
            ],
            [
                'label' => __('Support inbox'),
                'url' => SupportInquiryResource::getUrl('index'),
                'description' => __('Player inquiries'),
            ],
        ];
    }
}
