<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UpdateUserRequest extends FormRequest
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
            'username' => 'string|max:50|unique:users,username,' . $request->user()->id,
            'email_address' => 'email|unique:users,email_address,' . $request->user()->id,
            'phone_number' => 'string|unique:users,phone_number,' . $request->user()->id,
            'date_of_birth' => 'date|before:18 years ago',
            'current_password' => [function ($attribute, $value, $fail) use ($request) {
                if (!Hash::check($value, $request->user()->password)) {
                    return $fail(__(trans('passwords.incorrect')));
                }
            }],
            'password' => 'required_with:current_password|confirmed',
            'avatar' => 'mimes:jpg,jpeg,png,webp|max:3000',
        ];
    }
}
