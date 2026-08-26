<x-filament-panels::page>
    <div class="flex flex-col gap-6" wire:poll.2s="refreshSnapshots">

        {{-- Tenant picker --}}
        <x-filament::section
            heading="Tenant"
            description="Live ops channel: private-tenants.{tenant}.crash-ops — admins only. Player ticks omit committed crash targets and the PF nonce while a round runs."
        >
            <div class="mb-3 flex flex-wrap items-center justify-end gap-2">
                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Resources\CrashTenantSettings\CrashTenantSettingsResource::getUrl('index') }}"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-cog-6-tooth"
                >
                    Crash settings
                </x-filament::button>
            </div>
            <x-filament::input.wrapper class="max-w-md">
                <x-filament::input.select wire:model.live="tenantSlug">
                    @foreach($this->tenantOptions as $opt)
                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </x-filament::section>

        {{-- HTTP snapshot --}}
        <x-filament::section
            heading="Engine status"
            description="HTTP snapshot, refreshed every ~2s. Adjust engine / pause / next-round override / refunds under Gaming → Crash tenant settings."
        >
            <div class="flex flex-col gap-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-gray-500 dark:text-gray-400">Engine enabled</span>
                    <x-filament::badge :color="data_get($stateSnapshot, 'engine_enabled') ? 'success' : 'danger'">
                        {{ data_get($stateSnapshot, 'engine_enabled') ? 'yes' : 'no' }}
                    </x-filament::badge>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-gray-500 dark:text-gray-400">Game enabled</span>
                    <x-filament::badge :color="data_get($stateSnapshot, 'game_enabled') ? 'success' : 'danger'">
                        {{ data_get($stateSnapshot, 'game_enabled') ? 'yes' : 'no' }}
                    </x-filament::badge>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-gray-500 dark:text-gray-400">Paused</span>
                    <x-filament::badge :color="data_get($stateSnapshot, 'game_paused') ? 'warning' : 'gray'">
                        {{ data_get($stateSnapshot, 'game_paused') ? 'yes' : 'no' }}
                    </x-filament::badge>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-gray-500 dark:text-gray-400">Phase</span>
                    <x-filament::badge :color="match (data_get($stateSnapshot, 'round.phase')) {
                        'running' => 'warning',
                        'busted' => 'danger',
                        'betting' => 'info',
                        default => 'gray',
                    }">
                        {{ data_get($stateSnapshot, 'round.phase', '—') }}
                    </x-filament::badge>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-gray-500 dark:text-gray-400">Ladder multiplier (public)</span>
                    <span class="font-mono">{{ number_format((float) (data_get($stateSnapshot, 'multiplier_preview') ?? 1), 4) }}×</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-gray-500 dark:text-gray-400">Round pk</span>
                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ data_get($stateSnapshot, 'round.id') ?? '—' }}</span>
                </div>
            </div>
        </x-filament::section>

        {{-- WebSocket pulse --}}
        <x-filament::section heading="Live ops pulse (WebSocket)">
            <x-slot name="description">
                @if ($this->broadcastingEchoEnabled())
                    Echo is configured — this card updates from CrashGameOpsPulse events in real time.
                @else
                    Broadcasting is disabled or Filament Echo isn't configured. This page still polls every ~2s.
                    Set BROADCAST_CONNECTION and the Pusher/Soketi vars.
                @endif
            </x-slot>

            @if ($lastEchoPulse)
                <div class="flex flex-col gap-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">Phase</span>
                        <span class="font-mono">{{ $lastEchoPulse['phase'] ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">Display multiplier</span>
                        <span class="font-mono">{{ number_format((float) ($lastEchoPulse['display_multiplier'] ?? 0), 4) }}×</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">Committed crash (house)</span>
                        <span class="font-mono">
                            @if (($lastEchoPulse['committed_crash_multiplier'] ?? null) !== null)
                                {{ number_format((float) $lastEchoPulse['committed_crash_multiplier'], 4) }}×
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">Pending operator override</span>
                        <span class="font-mono">
                            @if (($lastEchoPulse['pending_operator_override_multiplier'] ?? null) !== null)
                                {{ number_format((float) $lastEchoPulse['pending_operator_override_multiplier'], 4) }}×
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">Open bets / stake</span>
                        <span class="font-mono">
                            {{ (int) ($lastEchoPulse['open_bet_count'] ?? 0) }}
                            ·
                            ${{ number_format(((int) ($lastEchoPulse['open_stake_minor_sum'] ?? 0)) / 100, 2) }}
                        </span>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Waiting for a CrashGameOpsPulse on the ops channel, or broadcasts are idle.
                </p>
            @endif
        </x-filament::section>

        {{-- Open bets --}}
        <x-filament::section heading="Open bets (live round)">
            @forelse ($openBets as $row)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/5">
                    <span class="font-medium text-gray-900 dark:text-white">{{ $row['user'] }}</span>
                    <div class="flex items-center gap-4 font-mono text-gray-600 dark:text-gray-300">
                        <span>${{ number_format(($row['stake_minor'] ?? 0) / 100, 2) }}</span>
                        <span class="text-gray-400 dark:text-gray-500">
                            @if (($row['auto'] ?? null) !== null)
                                auto {{ number_format((float) $row['auto'], 2) }}×
                            @else
                                no auto
                            @endif
                        </span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $row['placed_at'] ?? '—' }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No open bets for the active round (or no round running).
                </p>
            @endforelse
        </x-filament::section>
    </div>

    @if ($this->broadcastingEchoEnabled())
        @script
        <script>
            let opsSlug = null;
            const bind = () => {
                const slug = $wire.tenantSlug;
                if (!window.Echo || !slug) return;
                if (opsSlug === slug) return;
                if (opsSlug) {
                    try {
                        window.Echo.leave('private-tenants.' + opsSlug + '.crash-ops');
                    } catch (e) {}
                }
                opsSlug = slug;
                window.Echo.private('tenants.' + slug + '.crash-ops').listen('.CrashGameOpsPulse', (payload) =>
                    $wire.call('applyEchoPulse', payload),
                );
            };
            bind();
            $wire.watch('tenantSlug', () => bind());
        </script>
        @endscript
    @endif
</x-filament-panels::page>
