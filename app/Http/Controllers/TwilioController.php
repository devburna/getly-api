<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Twilio\Rest\Client;

class TwilioController extends Controller
{
    public function send($to, $body)
    {
        try {
            $account_sid = env("TWILLO_ACCOUNT_SID");
            $auth_token = env("TWILLO_AUTH_TOKEN");
            $twilio_number = env("TWILLO_NUMBER");

            $client = new Client($account_sid, $auth_token);
            $client->messages->create('+' . $to, [
                'from' => $twilio_number,
                'body' => $body
            ]);

            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }
}
