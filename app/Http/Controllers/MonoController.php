<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MonoController extends Controller
{
    private  $monoUrl, $monoSecKey;

    public function __construct()
    {
        $this->monoUrl = env('MONO_URL');
        $this->monoSecKey = env('MONO_SEC_KEY');
    }
    public function createVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->monoSecKey}",
            ])->post("{$this->monoUrl}/cards/virtual", [
                'currency' => 'NGN',
                'amount' => $data['amount'],
                'account_holder' => "{$data['first_name']} {$data['last_name']}",
            ])->json();

            // catch error
            if ($response['status'] === 'error') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set provider
            $data = $response['data'];
            $data['provider'] = 'mono';

            return $data;

            return $response['data'];
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
