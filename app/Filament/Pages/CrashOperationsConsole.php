<?php

namespace App\Filament\Pages;

use App\Models\CrashBet;
use App\Models\Tenant;
use App\Services\Crash\CrashGameStateService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CrashOperationsConsole extends Page
{
    protected string $view = 'filament.pages.crash-operations-console';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static UnitEnum|string|null $navigationGroup = 'Gaming';

    protected static ?string $navigationLabel = 'Crash live ops';

    protected static ?int $navigationSort = 12;

    public ?string $tenantSlug = null;

    /** @var array<string, mixed> */
    public array $stateSnapshot = [];

    /** @var array<string, mixed>|null */
    public ?array $lastEchoPulse = null;

    /** @var list<array<string, mixed>> */
    public array $openBets = [];

    public function mount(): void
    {
        $this->tenantSlug = Tenant::query()->orderBy('slug')->value('slug')
            ?? (string) config('gaming.default_tenant_slug', 'crashx');
        $this->refreshSnapshots();
    }

    public function updatedTenantSlug(?string $_): void
    {
        $this->refreshSnapshots();
    }

    public function applyEchoPulse(array $payload): void
    {
        $this->lastEchoPulse = $payload;
        $this->refreshSnapshots();
    }

    public function refreshSnapshots(): void
    {
        $slug = $this->tenantSlug;
        if (! is_string($slug) || $slug === '') {
            $this->stateSnapshot = [];

            return;
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            $this->stateSnapshot = [];

            return;
        }

        $this->stateSnapshot = app(CrashGameStateService::class)
            ->stateForTenant((string) $tenant->getKey());

        $roundPk = data_get($this->stateSnapshot, 'round.id');
        if (is_string($roundPk)) {
            $this->openBets = CrashBet::query()
                ->where('crash_round_id', $roundPk)
                ->where('status', 'open')
                ->with(['user:id,name'])
                ->orderByDesc('created_at')
                ->limit(40)
                ->get()
                ->map(fn (CrashBet $b): array => [
                    'id' => (string) $b->getKey(),
                    'user' => $b->user?->name ?? '—',
                    'stake_minor' => $b->stake_minor,
                    'auto' => $b->auto_cashout_multiplier,
                    'placed_at' => $b->created_at?->toIso8601String(),
                ])
                ->all();

            return;
        }

        $this->openBets = [];
    }

    public function broadcastingEchoEnabled(): bool
    {
        return config('broadcasting.default') !== 'null'
            && is_array(config('filament.broadcasting.echo'));
    }

    /** @return list<array{label:string,value:string}> */
    public function getTenantOptionsProperty(): array
    {
        return Tenant::query()
            ->orderBy('slug')
            ->get(['id', 'slug', 'display_name'])
            ->map(fn (Tenant $t): array => [
                'label' => $t->slug.' · '.$t->display_name,
                'value' => $t->slug,
            ])
            ->all();
    }
}
