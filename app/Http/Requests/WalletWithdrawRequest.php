<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WalletWithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'crypto_asset_id' => ['required', 'string', 'max:32'],
            'destination_address' => ['required', 'string', 'min:8', 'max:512'],
            'amount_minor' => ['required', 'integer', 'min:1'],
        ];
    }
}
