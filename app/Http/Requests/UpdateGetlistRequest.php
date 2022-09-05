<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGetlistRequest extends FormRequest
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
            'title' => 'string',
            'event_date' => 'date|after:30 minutes',
            'message' => 'string',
            'privacy' => 'boolean',
            'image' => 'mimes:jpeg,jpeg,png,webp|max:3000'
        ];
    }
}
