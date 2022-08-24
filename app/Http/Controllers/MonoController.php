<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonoController extends Controller
{
    private  $monoUrl, $monoSecKey;

    public function __construct()
    {
        $this->monoUrl = env('MONO_URL');
        $this->monoSecKey = env('MONO_SEC_KEY');
    }
    public function createVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->monoSecKey}",
            ])->post("{$this->monoUrl}/cards/virtual", [
                'currency' => 'NGN',
                'amount' => $data['amount'],
                'account_holder' => "{$data['first_name']} {$data['last_name']}",
            ])->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set provider
            $data = $response['data'];
            $data['id'] = $response['id'];
            $data['account_id'] = Str::uuid();
            $data['currency'] = $response['currency'];
            $data['card_hash'] = Str::uuid();
            $data['card_pan'] = $response['card_number'];
            $data['masked_pan'] = $response['card_pan'];
            $data['name_on_card'] = $response['name_on_card'];
            $data['expiration'] = "{$response['expiry_month']}/{$response['expiry_year']}";
            $data['cvv'] = $response['cvv'];
            $data['address_1'] = $response['address_1'];
            $data['address_2'] = null;
            $data['city'] = $response['billing_address']['street'];
            $data['state'] = $response['billing_address']['state'];
            $data['zip_code'] = $response['billing_address']['postal_code'];
            $data['callback_url'] = null;
            $data['is_active'] = true;
            $data['provider'] = 'mono';

            return $data;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
