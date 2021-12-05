<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContributorRequest extends FormRequest
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
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required_if:type,==,contribute|string|max:50',
            'email' => 'required_if:type,==,contribute|email',
            'phone' => 'required_if:type,==,contribute',
            'amount' => 'required_if:type,==,contribute|numeric',
            'type' => 'in:contribute,buy'
        ];
    }
}
