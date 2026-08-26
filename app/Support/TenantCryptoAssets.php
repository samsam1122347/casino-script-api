<?php

namespace App\Support;

final class TenantCryptoAssets
{
    /**
     * @return list<array{id: string, symbol: string, name: string, network: string, address: string, icon_key?: string, min_deposit_usd?: float, min_withdraw_usd?: float}>
     */
    public static function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $allowed = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = isset($row['id']) && is_string($row['id']) ? trim($row['id']) : '';
            if ($id === '' || strlen($id) > 32) {
                continue;
            }
            $symbol = isset($row['symbol']) && is_string($row['symbol']) ? trim($row['symbol']) : '';
            $name = isset($row['name']) && is_string($row['name']) ? trim($row['name']) : '';
            $network = isset($row['network']) && is_string($row['network']) ? trim($row['network']) : '';
            $address = isset($row['address']) && is_string($row['address']) ? trim($row['address']) : '';
            if ($symbol === '' || $name === '' || $network === '' || $address === '') {
                continue;
            }
            $address = mb_substr($address, 0, 512);
            $symbol = mb_substr($symbol, 0, 24);
            $name = mb_substr($name, 0, 128);
            $network = mb_substr($network, 0, 64);

            $out = [
                'id' => $id,
                'symbol' => $symbol,
                'name' => $name,
                'network' => $network,
                'address' => $address,
            ];
            $iconKey = $row['icon_key'] ?? null;
            if (is_string($iconKey) && $iconKey !== '') {
                $out['icon_key'] = mb_substr($iconKey, 0, 64);
            }
            foreach (['min_deposit_usd', 'min_withdraw_usd'] as $moneyKey) {
                if (! isset($row[$moneyKey])) {
                    continue;
                }
                $v = $row[$moneyKey];
                if (is_numeric($v)) {
                    $f = round((float) $v, 2);
                    if ($f > 0) {
                        $out[$moneyKey] = $f;
                    }
                }
            }
            $allowed[] = $out;
        }

        return $allowed;
    }

    /**
     * @param  list<array<string, mixed>>  $sanitized
     * @return array<string, mixed>|null
     */
    public static function findById(array $sanitized, string $id): ?array
    {
        foreach ($sanitized as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        return null;
    }
}
