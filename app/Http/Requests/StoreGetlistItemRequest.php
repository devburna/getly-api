<?php

namespace App\Http\Requests;

use App\Models\Getlist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class StoreGetlistItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $getlist = Getlist::find($request->getlist_id);
        return $getlist && $request->user()->is($getlist->user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'getlist_id' => 'required|exists:getlists,id',
            'name' => 'required|string',
            'price' => 'required|numeric',
            'quantity' => 'required|numeric',
            'details' => 'required|string',
            'image' => 'required|mimes:jpeg,jpeg,png,webp|max:3000'
        ];
    }
}
