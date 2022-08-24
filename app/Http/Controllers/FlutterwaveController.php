<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FlutterwaveController extends Controller
{
    private  $flutterwaveUrl, $flutterwaveSecKey, $provider;

    public function __construct()
    {
        $this->flutterwaveUrl = env('FLUTTERWAVE_URL');
        $this->flutterwaveSecKey = env('FLUTTERWAVE_SEC_KEY');
        $this->provider = 'flutterwave';
    }

    public function generatePaymentLink($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post("{$this->flutterwaveUrl}/payments", [
                'tx_ref' => Str::uuid(),
                'amount' => $data['amount'],
                'currency' => 'NGN',
                'redirect_url' => $data['redirect_url'],
                'meta' => $data['meta'],
                'customer' => [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phonenumber' => $data['phone_number']
                ],
                'customizations' => [
                    'title' => config('app.name'),
                    'logo' => asset('img/logo.png')
                ]
            ]);

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

    public function createVirtualAccount($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post("{$this->flutterwaveUrl}/virtual-account-numbers", [
                'trx_ref' =>  str_shuffle($data['id'] . config('app.name')),
                'email' => $data['email_address'],
                'is_permanent' => true,
                'bvn' => $data['bvn'],
                'phonenumber' => $data['phone_number'],
                'firstname' => $data['first_name'],
                'lastname' => $data['last_name'],
                'narration' => "{$data['first_name']} {$data['last_name']}"
            ]);

            // set response
            $responseData = $response->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set response data
            $responseData['data']['provider'] = $this->provider;

            // set response data
            $responseData['data']['provider'] = $this->provider;

            return $responseData;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function createVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post("{$this->flutterwaveUrl}/virtual-cards", [
                'currency' => 'NGN',
                'amount' => $data['amount'],
                'billing_name' => "{$data['first_name']} {$data['last_name']}",
            ]);

            // set response
            $responseData = $response->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set card data
            $responseData['data']['id'] = $response['id'];
            $responseData['data']['account_id'] = $response['account_id'];
            $responseData['data']['currency'] = $response['currency'];
            $responseData['data']['card_hash'] = $response['card_hash'];
            $responseData['data']['card_pan'] = $response['card_pan'];
            $responseData['data']['masked_pan'] = $response['masked_pan'];
            $responseData['data']['name_on_card'] = $response['name_on_card'];
            $responseData['data']['expiration'] = $response['expiration'];
            $responseData['data']['cvv'] = $response['cvv'];
            $responseData['data']['address_1'] = $response['address_1'];
            $responseData['data']['address_2'] = $response['address_2'];
            $responseData['data']['city'] = $response['city'];
            $responseData['data']['state'] = $response['state'];
            $responseData['data']['zip_code'] = $response['zip_code'];
            $responseData['data']['callback_url'] = $response['callback_url'];
            $responseData['data']['is_active'] = $response['is_active'];
            $responseData['data']['provider'] = 'flutterwave';

            return $responseData;
        } catch (\Throwable $th) {
            return (new MonoController())->createVirtualCard($data);
        }
    }

    public function fundVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post("{$this->flutterwaveUrl}/virtual-cards/{$data['card']}/fund", [
                'debit_currency' => 'NGN',
                'amount' => $data['amount'],
            ]);

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

    public function withdrawVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post("{$this->flutterwaveUrl}/virtual-cards/{$data['card']}/withdraw", [
                'amount' => $data['amount'],
            ]);

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

    public function virtualCardTransactions($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->get("{$this->flutterwaveUrl}/virtual-cards/{$data['card']}/transactions", $data);

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

    public function toggleVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->put("{$this->flutterwaveUrl}/virtual-cards/{$data['card']}/status/{$data['action']}");

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

    public function verifyTransaction($data)
    {
        try {

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->get("{$this->flutterwaveUrl}/transactions/{$data}/verify");

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

    public function verifyBvn($data)
    {
        try {
            $response =  Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->get("{$this->flutterwaveUrl}/kyc/bvns/{$data}");

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

    public function bankTransfer($data)
    {
        try {
            $data['debit_currency'] = "{$data['currency']}";
            $data['reference'] = Str::uuid();
            $data['narration'] = array_key_exists('narration', $data) ? $data['narration'] : null;
            $data['callback_url'] = route('flw-webhook');

            $response =  Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post("{$this->flutterwaveUrl}/transfers", $data);

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

    public function banks(Request $request)
    {
        try {
            $request->validate([
                'country' => 'required|in:ng,gh,ke,ug,za,tz'
            ]);

            $response =  Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->get("{$this->flutterwaveUrl}/banks/{$request->country}");

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

    public function bankBranches(Request $request)
    {
        try {
            $request->validate([
                'bank_id' => 'required|numeric'
            ]);

            $response =  Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->get("{$this->flutterwaveUrl}/banks/{$request->bank_id}/branches");

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

    public function bankDetails(Request $request)
    {
        try {
            $response =  Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post("{$this->flutterwaveUrl}/accounts/resolve", $request->all());

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
