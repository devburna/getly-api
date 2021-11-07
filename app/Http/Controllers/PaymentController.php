<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
            'Authorization' => 'Bearer FLWSECK_TEST-3f407c91b3396dc7040be5ead43693e9-X',
        ])->post('https://api.flutterwave.com/v3/payments', [
            'tx_ref' => str_shuffle(Str::random(40)),
            'amount' => $request->amount,
            'currency' => 'NGN',
            'redirect_url' => $request->redirect_url,
            'meta' => [
                'consumer_id' => 23,
                'consumer_mac' => '92a3-912ba-1192a',
                'item' => $request
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
}
