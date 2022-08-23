<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FlutterwaveController extends Controller
{
    private $flutterwaveSecKey;

    public function __construct()
    {
        $this->flutterwaveSecKey = env('FLUTTERWAVE_SEC_KEY');
    }

    public function generatePaymentLink($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post(env('FLUTTERWAVE_URL') . '/payments', [
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
            ])->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response['data'];
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
            ])->post(env('FLUTTERWAVE_URL') . '/virtual-account-numbers', [
                'trx_ref' =>  str_shuffle($data['id'] . config('app.name')),
                'email' => $data['email_address'],
                'is_permanent' => true,
                'bvn' => $data['bvn'],
                'phonenumber' => $data['phone_number'],
                'firstname' => $data['first_name'],
                'lastname' => $data['last_name'],
                'narration' => "{$data['first_name']} {$data['last_name']}"
            ])->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response['data'];
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
            ])->post(env('FLUTTERWAVE_URL') . '/virtual-cards', [
                'currency' => 'USD',
                'amount' => $data['amount'],
                'billing_name' => "{$data['first_name']} {$data['last_name']}",
            ])->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response['data'];
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function fundVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecKey}",
            ])->post(env('FLUTTERWAVE_URL') . "/virtual-cards/{$data['card']}/fund", [
                'debit_currency' => 'USD',
                'amount' => $data['amount'],
            ])->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response['data'];
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
            ])->post(env('FLUTTERWAVE_URL') . "/virtual-cards/{$data['card']}/withdraw", [
                'amount' => $data['amount'],
            ])->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response['data'];
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
            ])->get(env('FLUTTERWAVE_URL') . "/virtual-cards/{$data['card']}/transactions", $data)->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response['data'];
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
            ])->put(env('FLUTTERWAVE_URL') . "/virtual-cards/{$data['card']}/status/{$data['action']}")->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response['data'];
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
            ])->get(env('FLUTTERWAVE_URL') . "/transactions/{$data}/verify")->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response['data'];
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
            ])->get(env('FLUTTERWAVE_URL') . "/kyc/bvns/{$data}")->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response['data'];
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
