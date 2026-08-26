<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'user_id',
    'request_id',
    'subscribe_token',
    'message',
    'email',
    'client_message_id',
    'ip_address',
    'user_agent',
])]
class SupportInquiry extends Model
{
    use HasUuids;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SupportInquiryMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportInquiryMessage::class, 'support_inquiry_id');
    }
}
