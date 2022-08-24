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

            // get card details
            $details = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->monoSecKey}",
            ])->get("{$this->monoUrl}/cards/{$response['id']}")->json();

            // catch error
            if ($details['status'] === 'error') {
                throw ValidationException::withMessages([$details['message']]);
            }

            // set card data
            $data = $details['data'];
            $data['id'] = $details['id'];
            $data['account_id'] = Str::uuid();
            $data['currency'] = $details['currency'];
            $data['card_hash'] = Str::uuid();
            $data['card_pan'] = $details['card_number'];
            $data['masked_pan'] = $details['card_pan'];
            $data['name_on_card'] = $details['name_on_card'];
            $data['expiration'] = "{$details['expiry_month']}/{$details['expiry_year']}";
            $data['cvv'] = $details['cvv'];
            $data['address_1'] = "{$details['billing_address']['street']} {$details['billing_address']['state']}";
            $data['address_2'] = null;
            $data['city'] = $details['billing_address']['street'];
            $data['state'] = $details['billing_address']['state'];
            $data['zip_code'] = $details['billing_address']['postal_code'];
            $data['callback_url'] = route('payment');
            $data['is_active'] = true;
            $data['provider'] = 'mono';

            return $data;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
