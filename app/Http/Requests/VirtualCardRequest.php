<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class VirtualCardRequest extends FormRequest
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
            'amount' => ['required', function ($attribute, $value, $fail) use ($request) {
                if ($request->user()->wallet->balance < $request->amount) {
                    return $fail(__('Insufficient balance'));
                }
            }],
        ];
    }
}
