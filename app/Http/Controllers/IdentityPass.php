<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class IdentityPass extends Controller
{
    private $identityPassUrl, $identityPassKey, $identityPassAppId, $provider;

    public function __construct()
    {
        $this->identityPassUrl = env('IDENTITY_PASS_URL');
        $this->identityPassKey = env('IDENTITY_PASS_KEY');
        $this->identityPassAppId = env('IDENTITY_PASS_APP_ID');
        $this->provider = 'identify-pass';
    }

    public function verifyBvn($data)
    {
        try {
            $responseData =  Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->identityPassKey,
                'app-id' => $this->identityPassAppId,
            ])->post("{$this->identityPassUrl}/biometrics/merchant/data/verification/bvn", [
                'number' => $data
            ]);

            // set response
            $responseData = $responseData->json();

            // catch failed
            if (!$responseData['status']) {
                throw ValidationException::withMessages([array_key_exists('numbers', $responseData['detail']) ? $responseData['detail']['number'] : $responseData['detail']]);
            }

            // set response data
            $responseData['bvn_data']['provider'] = $this->provider;

            return $responseData;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
