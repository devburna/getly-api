<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGetlistRequest extends FormRequest
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
            'title' => 'required|string',
            'event_date' => 'required|date|after:30 minutes',
            'message' => 'string',
            'privacy' => 'required|boolean',
            'image' => 'required|mimes:jpeg,jpeg,png,webp|max:3000'
        ];
    }
}
