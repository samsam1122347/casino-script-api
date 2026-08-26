<?php

namespace App\Services\Profile;

final class VipProgressCalculator
{
    /**
     * @return array{
     *     tier_id: string,
     *     tier_label_key: string,
     *     tier_index: int,
     *     xp_total: int,
     *     xp_floor_current_tier: int,
     *     xp_ceiling_next_tier: int|null,
     *     progress_percent: int,
     *     xp_to_next: int,
     *     next_tier_bonus_minor: int,
     *     at_max: bool,
     * }
     */
    public function compute(int $depositsMinor): array
    {
        $per = max(1, (int) config('vip.xp_per_deposit_minor', 100));
        $xpTotal = intdiv(max(0, $depositsMinor), $per);

        /** @var list<array{id: string, label_key: string, min_xp: int, next_level_bonus_minor: int}> $tiersRaw */
        $tiersRaw = config('vip.tiers', []);

        /** @var list<array{id: string, label_key: string, min_xp: int, next_level_bonus_minor: int}> $tiers */
        $tiers = [];
        foreach ($tiersRaw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? (string) $row['id'] : '';
            $labelKey = isset($row['label_key']) ? (string) $row['label_key'] : $id;
            $minXp = isset($row['min_xp']) ? (int) $row['min_xp'] : 0;
            $bonusNext = isset($row['next_level_bonus_minor']) ? (int) $row['next_level_bonus_minor'] : 0;
            if ($id !== '') {
                $tiers[] = [
                    'id' => $id,
                    'label_key' => $labelKey !== '' ? $labelKey : $id,
                    'min_xp' => $minXp,
                    'next_level_bonus_minor' => max(0, $bonusNext),
                ];
            }
        }

        if ($tiers === []) {
            return [
                'tier_id' => 'none',
                'tier_label_key' => 'none',
                'tier_index' => 0,
                'xp_total' => $xpTotal,
                'xp_floor_current_tier' => 0,
                'xp_ceiling_next_tier' => null,
                'progress_percent' => 0,
                'xp_to_next' => 0,
                'next_tier_bonus_minor' => 0,
                'at_max' => false,
            ];
        }

        usort($tiers, fn (array $a, array $b): int => $a['min_xp'] <=> $b['min_xp']);

        $currentIndex = 0;
        for ($i = count($tiers) - 1; $i >= 0; $i--) {
            if ($xpTotal >= $tiers[$i]['min_xp']) {
                $currentIndex = $i;
                break;
            }
        }

        $current = $tiers[$currentIndex];
        $floor = $current['min_xp'];
        $hasNext = isset($tiers[$currentIndex + 1]);
        $atMax = ! $hasNext;

        $ceiling = $hasNext ? $tiers[$currentIndex + 1]['min_xp'] : null;
        /** @var int $nextTierBonusFromCurrentDefinition */
        $nextTierBonusFromCurrentDefinition = $current['next_level_bonus_minor'];

        if ($atMax || $ceiling === null || $ceiling <= $floor) {
            return [
                'tier_id' => $current['id'],
                'tier_label_key' => $current['label_key'],
                'tier_index' => $currentIndex,
                'xp_total' => $xpTotal,
                'xp_floor_current_tier' => $floor,
                'xp_ceiling_next_tier' => null,
                'progress_percent' => 100,
                'xp_to_next' => 0,
                'next_tier_bonus_minor' => 0,
                'at_max' => true,
            ];
        }

        $span = $ceiling - $floor;
        $within = $xpTotal - $floor;
        $progressPercent = $span > 0
            ? (int) min(100, max(0, (int) round(($within / $span) * 100)))
            : 0;

        $xpToNext = max(0, $ceiling - $xpTotal);

        return [
            'tier_id' => $current['id'],
            'tier_label_key' => $current['label_key'],
            'tier_index' => $currentIndex,
            'xp_total' => $xpTotal,
            'xp_floor_current_tier' => $floor,
            'xp_ceiling_next_tier' => $ceiling,
            'progress_percent' => $progressPercent,
            'xp_to_next' => $xpToNext,
            'next_tier_bonus_minor' => $nextTierBonusFromCurrentDefinition,
            'at_max' => false,
        ];
    }
}
