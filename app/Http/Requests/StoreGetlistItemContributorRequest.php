<?php

namespace App\Http\Requests;

use App\Enums\GetlistItemContributionType;
use Illuminate\Foundation\Http\FormRequest;
use BenSampo\Enum\Rules\EnumValue;

class StoreGetlistItemContributorRequest extends FormRequest
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
            'type' => ['required', new EnumValue(GetlistItemContributionType::class)],
            'full_name' => 'required|string',
            'email_address' => 'required|email',
            'phone_number' => 'required|string',
            'amount' => 'required_unless:type,' . GetlistItemContributionType::BUY() . '|numeric',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'amount.required_unless' => 'Please specify the amount you want to contribute.',
        ];
    }
}
