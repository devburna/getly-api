<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class SetPinRequest extends FormRequest
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
            'pin' => ['required', 'digits:4', function ($attribute, $value, $fail) use ($request) {
                if ($request->user()->profile->password) {
                    return $fail(__('Your pin has already been set.'));
                }
            }],
            'pin_confirmation' => 'same:pin',
        ];
    }
}
