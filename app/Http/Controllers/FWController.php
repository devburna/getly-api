<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FWController extends Controller
{
    public $fw_sec_key, $reference;

    public function __construct()
    {
        $this->fw_sec_key = 'FLWSECK_TEST-3f407c91b3396dc7040be5ead43693e9-X';
        $this->reference = str::uuid();
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

    public function create()
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('FW_URL') . "/virtual-cards",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => [
                    "currency" => "USD",
                    "amount" => 0,
                    "billing_name" => "Jermaine Graham",
                    "billing_address" => "333 fremont road",
                    "billing_city" => "San Francisco",
                    "billing_state" => "CA",
                    "billing_postal_code" => "984105",
                    "billing_country" => "US",
                    "callback_url" => "https://getly.heroku.com/"
                ],
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer " . env('FW_SEC_KEY')
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);

            return $response;
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
    }

    public function card($card)
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('FW_URL') . "/virtual-cards/" . $card,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer " . env('FW_SEC_KEY')
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return $response;
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
    }

    public function fund($card, $amount)
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('FW_URL') . "/virtual-cards/" . $card . "/fund",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => [
                    "debit_currency" => "NGN",
                    "amount" => $amount
                ],
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer " . env('FW_SEC_KEY')
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return $response;
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
    }

    public function transactions($card, $from, $to, $index, $size)
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('FW_URL') . "/virtual-cards/" . $card . "/transactions?from=" . $from . "&to=" . $to . "&index=" . $index . "&size=" . $size,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer " . env('FW_SEC_KEY')
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return $response;
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
    }


    public function withdraw($card, $amount)
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('FW_URL') . "/virtual-cards/" . $card,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => [
                    "amount" => $amount
                ],
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer " . env('FW_SEC_KEY')
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return $response;
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
    }

    public function blockUnblock($card, $action)
    {
        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => env('FW_URL') . "/virtual-cards/" . $card . "/status/" . $action,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer " . env('FW_SEC_KEY')
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            return $response;
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
    }
}
