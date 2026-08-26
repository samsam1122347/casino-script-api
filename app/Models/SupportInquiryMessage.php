<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'support_inquiry_id',
    'admin_id',
    'body',
    'is_from_admin',
])]
class SupportInquiryMessage extends Model
{
    use HasUuids;

    /** @return BelongsTo<SupportInquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(SupportInquiry::class, 'support_inquiry_id');
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** @phpstan-return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_from_admin' => 'boolean',
        ];
    }
}
