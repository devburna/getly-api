<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

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
    public function rules(Request $request)
    {
        return [
            'name' => 'required|string|max:50',
            'email' => 'required|email',
            'phone' => 'required',
            'amount' => 'required_if:type,==,contribute|numeric',
            'type' => 'in:contribute,buy'
        ];
    }
}
