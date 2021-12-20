<?php

namespace App\Http\Requests;

use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class SignupRequest extends FormRequest
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
            'full_name' => 'required|string|max:50|unique:users,name',
            'email' => 'required|email|unique:users,email',
            'phone_code' => 'required|integer',
            'phone' => ['required', 'digits:10', function ($attribute, $value, $fail) use ($request) {
                if (Profile::where('phone', $request->phone_code . $request->phone)->first()) {
                    return $fail(__('Phone number has already been taken.'));
                }
            }],
            'birthday' => 'required|date|before:today',
            'password' => 'required',
            'device_name' => 'required',
        ];
    }
}
