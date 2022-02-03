<?php

namespace App\Http\Controllers;

use App\Models\VirtualCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FWController extends Controller
{
    public $fw_sec_key, $reference;

    public function __construct()
    {
        $this->fw_sec_key = 'FLWSECK_TEST-3f407c91b3396dc7040be5ead43693e9-X';
        $this->reference = Str::uuid();
    }

    public function generatePaymentLink($amount, $name, $email, $phone, $description)
    {
        $response =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->fw_sec_key,
        ])->post('https://api.flutterwave.com/v3/payments', [
            'tx_ref' => $this->reference,
            'amount' => $amount,
            'currency' => 'NGN',
            'redirect_url' => env('APP_URL') . '/payment',
            'meta' => [
                'consumer_id' => 23,
                'consumer_mac' => '92a3-912ba-1192a',
            ],
            'customer' => [
                'email' => $email,
                'phone_number' => $phone,
                'name' => $name,
            ],
            'customizations' => [
                'title' => config('app.name'),
                'description' => $description,
                'logo' => asset('img/logo.png'),
            ],
        ]);

        if ($response->status() === 200) {
            $link = $response->json();

            return [
                'data' => [
                    'link' => $link['data']['link'],
                    'reference' => $this->reference
                ],
                'message' => $link['message']
            ];
        } else {
            return null;
        }
    }

    public function verifyPayment($transaction_id)
    {
        $response =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->fw_sec_key,
        ])->get('https://api.flutterwave.com/v3/transactions/' . $transaction_id . '/verify');

        if ($response->status() === 200) {
            return $response->json();
        } else {
            return null;
        }
    }

    public function createVirtualCard($name, $amount)
    {
        $response =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->fw_sec_key,
        ])->get('https://api.flutterwave.com/v3/virtual-cards', [
            'currency' => 'USD',
            'amount' => $amount,
            'billing_name' => $name,
            'billing_address' => '333 fremont road',
            'billing_city' => 'San Francisco',
            'billing_state' => 'CA',
            'billing_postal_code' => '984105',
            'billing_country' => 'US',
            'callback_url' => route('flw-webhook'),
        ]);

        if ($response->status() === 200) {
            return $response->json();
        } else {
            return null;
        }
    }

    public function getVirtualCard($card)
    {
        $response =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->fw_sec_key,
        ])->get('https://api.flutterwave.com/v3/virtual-cards/' . $card);

        if ($response->status() === 200) {
            return $response->json();
        } else {
            return null;
        }
    }

    public function fundVirtualCard($card, $amount)
    {
        $response =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->fw_sec_key,
        ])->post('https://api.flutterwave.com/v3/virtual-cards/' . $card . '/fund', [
            'debit_currency' => 'NGN',
            'amount' => $amount
        ]);

        if ($response->status() === 200) {
            return $response->json();
        } else {
            return null;
        }
    }

    public function withdrawVirtualCard($card, $amount)
    {
        $response =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->fw_sec_key,
        ])->post('https://api.flutterwave.com/v3/virtual-cards/' . $card . '/withdraw', [
            'amount' => $amount
        ]);

        if ($response->status() === 200) {
            return $response->json();
        } else {
            return null;
        }
    }

    public function virtualCardTransactions($card, $from, $to, $index, $size)
    {
        $response =  Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->fw_sec_key,
        ])->post('https://api.flutterwave.com/v3/virtual-cards/' . $card . '/transactions', [
            'from' => $from,
            'to' => $to,
            'index' => $index,
            'size' => $size,
        ]);

        if ($response->status() === 200) {
            return $response->json();
        } else {
            return null;
        }
    }

    public function webHook(Request $request)
    {
        switch ($request['Type']) {
            case 'Notification':
                $card = VirtualCard::where('reference', $request['CardId'])->first();

                if ($card) {
                    // send sms
                    return (new TwilioController())->send($card->user->profile->phone, 'OTP', $request['Otp']);
                }
                break;

            default:
                # code...
                break;
        }
    }
}
