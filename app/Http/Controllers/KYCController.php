<?php

namespace App\Http\Controllers;

use App\Http\Requests\KYCRequest;
use Illuminate\Support\Facades\Http;

class KYCController extends Controller
{
    private $flutterwaveSecKey;

    public function __construct()
    {
        $this->flutterwaveSecKey = env('FLUTTERWAVE_SEC_KEY');
    }

    // bvn
    public function bvn(KYCRequest $request)
    {
        // send request to flutterwave.com
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->get(env('FLUTTERWAVE_URL') . "/kyc/bvns/{$request->bvn}");

        if (!$response->ok()) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => "Error occured, please contact support.",
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $response->json(),
            'message' => 'success',
        ]);
    }
}
