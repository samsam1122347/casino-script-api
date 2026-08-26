<?php

namespace App\Filament\Resources\CrashTenantSettings\Pages;

use App\Filament\Resources\CrashTenantSettings\CrashTenantSettingsResource;
use App\Models\CrashBet;
use App\Models\CrashRound;
use App\Models\CrashTenantSettings;
use App\Services\Crash\CrashOperatorAudit;
use App\Services\Crash\Engine\CrashRoundEngine;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCrashTenantSettings extends EditRecord
{
    protected static string $resource = CrashTenantSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pause_game')
                ->label('Pause gameplay')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var CrashTenantSettings $record */
                    $record = $this->record;
                    $record->game_paused = true;
                    $record->save();

                    $adminId = auth()->user()?->getAuthIdentifier();
                    if (is_numeric($adminId)) {
                        CrashOperatorAudit::log((int) $adminId, (string) $record->tenant_id, 'pause_game', []);
                    }

                    Notification::make()->title('Crash gameplay paused')->success()->send();
                }),

            Action::make('resume_game')
                ->label('Resume gameplay')
                ->action(function (): void {
                    /** @var CrashTenantSettings $record */
                    $record = $this->record;
                    $record->game_paused = false;
                    $record->save();

                    $adminId = auth()->user()?->getAuthIdentifier();
                    if (is_numeric($adminId)) {
                        CrashOperatorAudit::log((int) $adminId, (string) $record->tenant_id, 'resume_game', []);
                    }

                    Notification::make()->title('Crash gameplay resumed')->success()->send();
                }),

            Action::make('cancel_latest_betting_round')
                ->label('Cancel betting + refund stakes')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (CrashRoundEngine $engine): void {
                    /** @var CrashTenantSettings $record */
                    $record = $this->record;

                    /** @var CrashRound|null $round */
                    $round = CrashRound::query()
                        ->where('tenant_id', $record->tenant_id)
                        ->where('phase', 'betting')
                        ->orderByDesc('started_at')
                        ->first();

                    if ($round === null) {
                        Notification::make()->title('No betting round to cancel.')->danger()->send();

                        return;
                    }

                    $engine->cancelBettingRoundWithRefunds((string) $round->getKey());

                    $adminId = auth()->user()?->getAuthIdentifier();
                    if (is_numeric($adminId)) {
                        CrashOperatorAudit::log((int) $adminId, (string) $record->tenant_id, 'cancel_betting_round', [
                            'crash_round_id' => (string) $round->getKey(),
                        ]);
                    }

                    Notification::make()->title('Betting cancelled; stakes refunded.')->success()->send();
                }),

            Action::make('engine_tick_once')
                ->label('Engine tick once')
                ->requiresConfirmation()
                ->action(function (CrashRoundEngine $engine): void {
                    /** @var CrashTenantSettings $record */
                    $record = $this->record;

                    if (! config('crash.engine.enabled', false)) {
                        Notification::make()->title('Set CRASH_ENGINE_ENABLED=true before ticking.')->danger()->send();

                        return;
                    }

                    $tenant = $record->tenant()->first();
                    if ($tenant === null) {
                        Notification::make()->title('Tenant missing.')->danger()->send();

                        return;
                    }

                    $engine->tickTenant($tenant);

                    Notification::make()->title('Engine tick executed.')->success()->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var CrashTenantSettings $record */
        $record = $this->record;

        $record->loadMissing('tenant');

        $data['tenant_slug_view'] = (string) ($record->tenant?->slug ?? '');

        $openExposure = (int) CrashBet::query()
            ->whereHas('round', fn ($q) => $q->where('tenant_id', $record->tenant_id))
            ->where('status', 'open')
            ->sum('stake_minor');

        $data['_open_stake_exposure_minor'] = $openExposure;

        return $data;
    }
}
