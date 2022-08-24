<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonoController extends Controller
{
    private $monoUrl, $monoSecKey, $provider;

    public function __construct()
    {
        $this->monoUrl = env('MONO_URL');
        $this->monoSecKey = env('MONO_SEC_KEY');
        $this->provider = 'mono';
    }
    public function createVirtualCard($data)
    {
        try {
            $responseData = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->monoSecKey}",
            ])->post("{$this->monoUrl}/cards/virtual", [
                'currency' => 'NGN',
                'amount' => $data['amount'],
                'account_holder' => "{$data['first_name']} {$data['last_name']}",
            ]);

            // set response
            $responseData = $responseData->json();

            // catch error
            if ($responseData['status'] === 'error') {
                throw ValidationException::withMessages([$responseData['message']]);
            }

            // get card details
            $details = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->monoSecKey}",
            ])->get("{$this->monoUrl}/cards/{$responseData['data']['id']}");

            // set response
            $details = $details->json();

            // catch error
            if ($details['status'] === 'error') {
                throw ValidationException::withMessages([$details['message']]);
            }

            // set card data
            $responseData['data']['id'] = $details['data']['id'];
            $responseData['data']['account_id'] = Str::uuid();
            $responseData['data']['currency'] = $details['data']['currency'];
            $responseData['data']['card_hash'] = Str::uuid();
            $responseData['data']['card_pan'] = $details['data']['card_number'];
            $responseData['data']['masked_pan'] = $details['data']['card_pan'];
            $responseData['data']['name_on_card'] = $details['data']['name_on_card'];
            $responseData['data']['expiration'] = "{$details['data']['expiry_month']}/{$details['data']['expiry_year']}";
            $responseData['data']['cvv'] = $details['data']['cvv'];
            $responseData['data']['address_1'] = "{$details['data']['billing_address']['street']} {$details['data']['billing_address']['state']}";
            $responseData['data']['address_2'] = null;
            $responseData['data']['city'] = $details['data']['billing_address']['street'];
            $responseData['data']['state'] = $details['data']['billing_address']['state'];
            $responseData['data']['zip_code'] = $details['data']['billing_address']['postal_code'];
            $responseData['data']['callback_url'] = route('payment');
            $responseData['data']['is_active'] = true;
            $responseData['data']['provider'] = $this->provider;

            return $responseData;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function fundVirtualCard($data)
    {
        try {
            $responseData = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->monoSecKey}",
            ])->post("{$this->monoUrl}/cards/{$data['card']}/fund", [
                'fund_source' => 'NGN',
                'amount' => $data['amount'],
                'meta' => $data['meta']
            ]);

            // set response
            $responseData = $responseData->json();

            // catch error
            if ($responseData['status'] === 'error') {
                throw ValidationException::withMessages([$responseData['message']]);
            }

            // set response data
            $responseData['data']['provider'] = $this->provider;

            return $responseData;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function virtualCardTransactions($data)
    {
        try {
            unset($data['index']);
            unset($data['size']);
            $responseData = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->monoSecKey}",
            ])->get("{$this->monoUrl}/cards/{$data['card']}", $data);

            // set response
            $responseData = $responseData->json();

            // catch error
            if ($responseData['status'] === 'error') {
                throw ValidationException::withMessages([$responseData['message']]);
            }

            // set response data
            $responseData['data']['provider'] = $this->provider;

            return $responseData;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function verifyBvn($data)
    {
        try {
            $responseData =  Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->monoSecKey}",
            ])->get("{$this->monoUrl}/lookup/bvn", [
                'bvn' => $data
            ]);

            // set response
            $responseData = $responseData->json();

            // catch error
            if ($responseData['status'] === 'error') {
                throw ValidationException::withMessages([$responseData['message']]);
            }

            // set response data
            $responseData['data']['provider'] = $this->provider;

            return $responseData;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
