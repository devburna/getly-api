<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class MonoController extends Controller
{
    private $monoUrl, $monoSecKey, $provider;

    public function __construct()
    {
        $this->monoUrl = env('MONO_URL');
        $this->monoSecKey = env('MONO_SEC_KEY');
        $this->provider = 'mono';
    }

    public function verifyBvn($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->post("{$this->monoUrl}/v2/lookup/bvn", [
                'bvn' => $data
            ])->json();

            // catch error
            if (!array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set data provider
            $response['data']['provider'] = $this->provider;

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function createVirtualAccount($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->post("{$this->monoUrl}/issuing/v1/virtualaccounts", [
                'account_holder' => $data['identity'],
                'account_type' => 'deposit',
                'provider' => 'providus'
            ])->json();

            // catch error
            if (array_key_exists('status', $response) || $response['status'] === 'failed') {

                (new MonoAccountHolderController())->destroy($data);

                throw ValidationException::withMessages([$response['message']]);
            }

            // set data provider
            $response['data']['provider'] = $this->provider;

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function virtualAccountTransfer($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->post("{$this->monoUrl}/issuing/v1/virtualaccounts/{$data['cust']}/transfer", [
                'amount' => $data['amount'] * 100,
                'narration' => $data['narration'],
                'reference' => Str::random(24),
                'account_number' => $data['account_number'],
                'bank_code' => $data['bank'],
                'meta' => [
                    'cust' => $data['cust']
                ]
            ])->json();

            // catch error
            if (array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set data provider
            $response['data']['provider'] = $this->provider;

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function virtualAccountDetails($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->get("{$this->monoUrl}/issuing/v1/virtualaccounts/{$data}")->json();

            // catch error
            if (array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function createVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->post("{$this->monoUrl}/issuing/v1/cards/virtual", [
                'account_holder' => $data,
                'currency' => 'NGN',
                'amount' => env('MONO_VIRTUAL_CARD_FEE') * 100,
                'fund_source' => 'ngn'
            ])->json();

            // catch error
            if (array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set data provider
            $response['data']['provider'] = $this->provider;

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function virtualCardDetails($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->get("{$this->monoUrl}/issuing/v1/cards/{$data}")->json();

            // catch error
            if (array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function fundVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->post("{$this->monoUrl}/issuing/v1/cards/{$data['card']}/fund", [
                'amount' => $data['amount'] * 100,
                'fund_source' => 'ngn'
            ])->json();

            // catch error
            if (array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            // set data provider
            $response['data']['provider'] = $this->provider;

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function virtualCardTransactions($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->post("{$this->monoUrl}/issuing/v1/cards/{$data['card']}/transactions", [
                'page' => $data['page'],
                'from' => $data['from'],
                'to' => $data['to']
            ])->json();

            // catch error
            if (array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function toggleVirtualCard($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->patch("{$this->monoUrl}/issuing/v1/cards/{$data['card']}/{$data['action']}")->json();

            // catch error
            if (array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function banks()
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->get("{$this->monoUrl}/v1/misc/banks")->json();

            // catch error
            if (array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    public function verifyBank($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->get("{$this->monoUrl}/v1/lookup/accountnumber", [
                'bank_code' => $data['bank'],
                'account_number' => $data['account_number']
            ])->json();

            // catch error
            if (array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
