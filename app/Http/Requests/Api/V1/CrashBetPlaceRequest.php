<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CrashBetPlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stake_minor' => ['required', 'integer', 'min:1'],
            'auto_cashout_multiplier' => ['nullable', 'numeric', 'min:1.01', 'max:100000'],
        ];
    }
}
