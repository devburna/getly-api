<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FlutterwaveController extends Controller
{
    private $flutterwaveUrl, $flutterwaveSecretKey;

    public function __construct()
    {
        $this->flutterwaveSecretKey = env('FLUTTERWAVE_SECRET_KEY');
        $this->flutterwaveUrl = env('FLUTTERWAVE_URL');
    }

    // generate payment link
    public function generatePaymentLink($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecretKey}"
            ])->post("{$this->flutterwaveUrl}/payments", [
                'tx_ref' => Str::uuid(),
                'amount' => $data['amount'],
                'currency' => 'NGN',
                'redirect_url' => $data['redirect_url'],
                'meta' => $data['meta'],
                'customer' => [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phonenumber' => $data['phone']
                ],
                'customizations' => [
                    'title' => config('app.name'),
                    'logo' => asset('img/logo.png')
                ],
            ])->json();

            // catch error
            if (!array_key_exists('status', $response) || ($response['status'] !== 'success')) {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    // verify transaction
    public function verifyTransaction($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->flutterwaveSecretKey}"
            ])->get("{$this->flutterwaveUrl}/transactions/{$data}/verify")->json();

            // catch error
            if (!array_key_exists('status', $response) || ($response['status'] !== 'success')) {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
