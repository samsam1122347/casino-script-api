<?php

namespace App\Services\Crash\Engine;

final class CrashPointSampler
{
    /**
     * Map uniform U ∈ (0,1) to crash multiplier given house-edge fraction ∈ [0,0.99).
     * Uses (1 - edge)/(1-U) clipped to [minMult, maxCap].
     */
    public function multiplierFromUniform(float $u, float $houseEdgeFraction, float $minMult, float $maxCap): float
    {
        $edge = max(0.0, min(0.99, $houseEdgeFraction));
        $cap = max($minMult, $maxCap);
        $eps = 1e-12;
        $u = max($eps, min(1 - $eps, $u));

        $raw = (1 - $edge) / (1 - $u);

        return round(max($minMult, min((float) $cap, $raw)), 4);
    }

    /**
     * Uniform (0,1) from cryptographic random (non-PF).
     */
    public function randomUniform(): float
    {
        $eps = 1e-12;
        $n = PHP_INT_MAX;

        /** @phpstan-ignore argument.type ($n is PHP_INT_MAX) */
        return max($eps, min(1 - $eps, random_int(1, $n) / ($n + 1.0)));
    }

    /**
     * Deterministic uniform (0,1) for provably-fair audit (HMAC-SHA256).
     */
    public function uniformFromPf(string $serverSeed, string $publicNonce, string $roundExternalId): float
    {
        $hex = hash_hmac('sha256', $roundExternalId.'|'.$publicNonce, $serverSeed);
        $slice = substr($hex, 0, 14);
        $i = hexdec($slice);
        $den = hexdec(str_repeat('f', strlen($slice)));
        $eps = 1e-12;
        $u = ($den > 0) ? (($i + 0.5) / ($den + 1)) : 0.5;

        return max($eps, min(1 - $eps, $u));
    }
}
