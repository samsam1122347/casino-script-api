<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $emailVerified = $this->email_verified_at;

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $emailVerified?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'verification_level' => $emailVerified ? 'email_verified' : 'unverified',
            'tenant_id' => $this->tenant_id !== null ? (string) $this->tenant_id : null,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => (string) $this->tenant->id,
                'slug' => $this->tenant->slug,
                'display_name' => $this->tenant->display_name,
            ]),
            'wallet' => $this->whenLoaded('wallet', fn () => [
                'id' => (string) $this->wallet->id,
                'currency' => $this->wallet->currency,
                'balance_minor' => $this->wallet->balance_minor,
                'balance' => $this->wallet->balanceMajor(),
            ]),
        ];
    }
}
