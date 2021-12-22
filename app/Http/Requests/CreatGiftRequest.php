<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatGiftRequest extends FormRequest
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
            'name' => 'required|string',
            'price' => 'required|numeric',
            'quantity' => 'required|numeric',
            'photo' => 'required|mimes:jpg,jpeg,png',
            'short_message' => 'string|max:100',
        ];
    }
}
