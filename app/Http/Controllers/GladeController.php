<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GladeController extends Controller
{
    public $key, $mid;

    public function __construct()
    {
        $this->key =  env('GLADE_MERCHANT_KEY');
        $this->mid =  env('GLADE_MERCHANT_ID');
    }

    public function createVirtualCard(Request $request)
    {
        try {
            $card = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->put('https://api.glade.ng/virtualcard', [
                'action' => 'create',
                'billing' => [
                    'address' => '333 Fremont Road',
                    'name' => $request->name,
                    'city' => 'San Fransisco',
                    'state' => 'California',
                    'postal_code' => '94124',
                ],
                'amount' => '1000',
                'currency' => 'NGN',
                'country' => 'NG',
            ])->json();

            return response()->json([
                'status' => true,
                'data' => $card,
                'message' => 'Created'
            ]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
