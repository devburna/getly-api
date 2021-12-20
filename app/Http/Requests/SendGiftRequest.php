<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class SendGiftRequest extends FormRequest
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
            'name' => 'required|string',
            'price' => ['required', function ($attribute, $value, $fail) use ($request) {
                if ($request->user()->wallet->balance < $request->price) {
                    return $fail(__('Insufficient balance'));
                }
            }],
            'quantity' => 'required|numeric',
            'image' => 'required|mimes:jpg,jpeg,png',
            'link' => 'required|url',
            'receiver_name' => 'required|string',
            'receiver_email' => 'required|email',
            'receiver_phone' => 'required|numeric',
            'short_message' => 'string|max:100',
        ];
    }
}
