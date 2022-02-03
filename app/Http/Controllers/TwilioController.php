<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Twilio\Rest\Client;

class TwilioController extends Controller
{
    public $account_sid, $auth_token, $twilio_number;

    public function __construct()
    {
        $this->account_sid = env("TWILLO_ACCOUNT_SID");
        $this->auth_token = env("TWILLO_AUTH_TOKEN");
        $this->twilio_number = env("TWILLO_NUMBER");
    }

    public function sms($to, $body)
    {
        try {
            $client = new Client($this->account_sid, $this->auth_token);
            $client->messages->create('+' . $to, [
                'from' => $this->twilio_number,
                'body' => $body
            ]);

            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function whatsapp($to, $body)
    {
        try {
            $client = new Client($this->account_sid, $this->auth_token);
            $client->messages->create('whatsapp:+' . $to, [
                'from' => 'whatsapp:' . $this->twilio_number,
                'body' => $body
            ]);

            return true;
        } catch (\Throwable $th) {
            return $th;
        }
    }
}
