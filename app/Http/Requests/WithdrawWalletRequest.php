<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawWalletRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'currency' => 'required|string|in:ngn',
            'amount' => 'required|numeric',
            // ngn required paylaod
            'ngn.account_bank' => 'required_if:currency,ngn|numeric',
            'ngn.account_number' => 'required_if:currency,ngn',
            'ngn.amount' => 'required_if:currency,ngn|numeric|same:amount',
            'ngn.currency' => 'required_if:currency,ngn|string|same:currency',
        ];
    }
}
