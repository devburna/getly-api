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
            $responseData = $details['data'];
            $responseData['id'] = $details['id'];
            $responseData['account_id'] = Str::uuid();
            $responseData['currency'] = $details['currency'];
            $responseData['card_hash'] = Str::uuid();
            $responseData['card_pan'] = $details['card_number'];
            $responseData['masked_pan'] = $details['card_pan'];
            $responseData['name_on_card'] = $details['name_on_card'];
            $responseData['expiration'] = "{$details['expiry_month']}/{$details['expiry_year']}";
            $responseData['cvv'] = $details['cvv'];
            $responseData['address_1'] = "{$details['billing_address']['street']} {$details['billing_address']['state']}";
            $responseData['address_2'] = null;
            $responseData['city'] = $details['billing_address']['street'];
            $responseData['state'] = $details['billing_address']['state'];
            $responseData['zip_code'] = $details['billing_address']['postal_code'];
            $responseData['callback_url'] = route('payment');
            $responseData['is_active'] = true;
            $responseData['provider'] = $this->provider;

            return $responseData;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function fundVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->monoSecKey}",
            ])->post("{$this->monoUrl}/cards/{$data['card']}/fund", [
                'fund_source' => 'NGN',
                'amount' => $data['amount'],
                'meta' => $data['meta']
            ])->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set provider
            $responseData = $response['data'];
            $responseData['provider'] = $this->provider;

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
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->get("{$this->monoUrl}/cards/{$data['card']}", $data)->json();

            // set response
            $responseData = $response->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set response data
            $responseData['data']['provider'] = $this->provider;

            return $responseData;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
