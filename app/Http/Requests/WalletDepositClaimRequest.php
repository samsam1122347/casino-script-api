<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletDepositClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'max:50'],
            'network' => ['required', 'string', 'max:50'],
        ];
    }
}
