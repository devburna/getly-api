<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class UpdateProfileRequest extends FormRequest
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
            'birthday' => 'date|before:today',
            'phone_code' => 'integer',
            'phone' => 'required_with:phone_code|digits:10|unique:profiles,phone,' . $request->user()->profile->id,
            'name' => 'max:50',
        ];
    }
}
