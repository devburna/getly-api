<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Union54Controller extends Controller
{
    public $url, $token;

    public function __construct()
    {
        $this->url = env('UNION54_URL');
        $this->token = env('UNION54_TOKEN');
    }

    public function registerUser(Request $request)
    {
        $response = Http::withHeaders([
            "Accept" => "application/json",
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . $this->token
        ])->post($this->url . '/user/register', [
            "firstName" => explode(' ', $request->user()->name)[0],
            "lastName" => explode(' ', $request->user()->name)[1],
            "kycCountry" => "USA",
            "uid" => 'tiimmyburner@gmail.com',
            "address" => "333 Fremont Road",
            "city" => "San Francisco",
            "postalCode" => "94124",
        ]);

        switch ($response->status()) {
            case 201:
                return $response->json();

                break;

            default:
                return null;
                break;
        }
    }

    public function createVirtualCard(Request $request)
    {
        // if (!$register = $this->registerUser($request)) {
        //     return null;
        // }

        $response = Http::withHeaders([
            "Accept" => "application/json",
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . $this->token
        ])->post($this->url . "/card/virtual", [
            "u54UserId" => "f6532255-0524-4b3f-9bb3-2df3ece12df7",
            "cardType" => "virtual",
            "currency" => "USD",
            "expiry" => now()->addYear(2)->year."-07-24",
            "singleUse" => false,
            "authType" => "INTEGRATOR",
        ]);


        switch ($response->status()) {
            case 201:
                return $response->json();

                break;

            default:
                return $response->json();
                break;
        }
    }

    public function getVitualCard(Request $request)
    {
    }
}
