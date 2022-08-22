<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FlutterwaveController extends Controller
{
    private $flutterwaveSecKey;

    public function __construct()
    {
        $this->flutterwaveSecKey = env('FLUTTERWAVE_SEC_KEY');
    }

    public function generatePaymentLink($data)
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->post(env('FLUTTERWAVE_URL') . '/payments', [
            'tx_ref' => Str::uuid(),
            'amount' => $data['amount'],
            'currency' => 'NGN',
            'redirect_url' => $data['redirect_url'],
            'meta' => [
                'consumer_id' => 23,
                'consumer_mac' => "92a3-912ba-1192a",
            ],
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
    }

    public function createVirtualAccount($data)
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->post(env('FLUTTERWAVE_URL') . '/virtual-account-numbers', [
            'trx_ref' =>  str_shuffle($data['id'] . config('app.name')),
            'email' => $data['email_address'],
            'is_permanent' => true,
            'bvn' => $data['bvn'],
            'phonenumber' => $data['phone_number'],
            'firstname' => $data['first_name'],
            'lastname' => $data['last_name'],
            'narration' => "{$data['first_name']} {$data['last_name']}"
        ]);
    }

    public function createVirtualCard($data)
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->post(env('FLUTTERWAVE_URL') . '/virtual-cards', [
            'currency' => 'USD',
            'amount' => $data['amount'],
            'billing_name' => "{$data['first_name']} {$data['last_name']}",
        ]);
    }

    public function verifyBvn($data)
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->get(env('FLUTTERWAVE_URL') . "/kyc/bvns/{$data['bvn']}");
    }

    public function fundVirtualCard($data)
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->post(env('FLUTTERWAVE_URL') . "/virtual-cards/{$data['card']}/fund", [
            'debit_currency' => 'USD',
            'amount' => $data['amount'],
        ]);
    }

    public function withdrawVirtualCard($data)
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->post(env('FLUTTERWAVE_URL') . "/virtual-cards/{$data['card']}/withdraw", [
            'amount' => $data['amount'],
        ]);
    }

    public function virtualCardTransactions($data)
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->get(env('FLUTTERWAVE_URL') . "/virtual-cards/{$data['card']}/transactions", $data);
    }

    public function toggleVirtualCard($data)
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->put(env('FLUTTERWAVE_URL') . "/virtual-cards/{$data['card']}/status/{$data['action']}");
    }
}
