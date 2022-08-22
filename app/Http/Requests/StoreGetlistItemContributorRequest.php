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
            'meta.contribute.amount' => 'required_if:type,' . GetlistItemContributionType::CONTRIBUTE() . '|numeric',
        ];
    }
}
