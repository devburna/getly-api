<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public $fw_sec_key;

    public function __construct()
    {
        $this->fw_sec_key = 'FLWSECK_TEST-3f407c91b3396dc7040be5ead43693e9-X';
    }

    public function generateFwPaymentLink(Request $request)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->fw_sec_key,
        ])->post('https://api.flutterwave.com/v3/payments', [
            'tx_ref' => $request->reference,
            'amount' => $request->amount,
            'currency' => 'NGN',
            'redirect_url' => $request->redirect_url,
            'meta' => [
                'consumer_id' => 23,
                'consumer_mac' => '92a3-912ba-1192a',
                'item' => $request->all()
            ],
            'customer' => [
                'email' => strtolower($request->customer_email),
                'phonenumber' => $request->customer_phone,
                'name' => ucfirst($request->customer_name),
            ],
            'customizations' => [
                'title' => config('app.name'),
                'description' => ucfirst($request->description),
                'logo' => asset('img/logo.png'),
            ],
        ])->json();
    }

    public function verifyFwPaymentLink(Request $request)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->fw_sec_key,
        ])->get('https://api.flutterwave.com/v3/transactions/' . $request->transaction_id . '/verify')->json();
    }
}
