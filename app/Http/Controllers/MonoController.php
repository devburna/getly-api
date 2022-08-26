<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

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
            ])->post("{$this->monoUrl}/issuing/v1/accountholders", [
                'account_holder' => $data,
                'account_type' => 'collection',
                'disposable' => false,
                'provider' => 'gtb'
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

    public function virtualAccountDetails($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->get("{$this->monoUrl}/issuing/v1/virtualaccounts/{$data}")->json();

            // catch error
            if (!array_key_exists('status', $response) || $response['status'] === 'failed') {
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
                'currency' => 'USD',
                'amount' => 5,
                'fund_source' => 'USD'
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

    public function virtualCardDetails($data)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'mono-sec-key' => $this->monoSecKey,
            ])->get("{$this->monoUrl}/issuing/v1/cards/{$data}")->json();

            // catch error
            if (!array_key_exists('status', $response) || $response['status'] === 'failed') {
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
                'amount' => $data['amount'],
                'fund_source' => 'USD'
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
            if (!array_key_exists('status', $response) || $response['status'] === 'failed') {
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
            if (!array_key_exists('status', $response) || $response['status'] === 'failed') {
                throw ValidationException::withMessages([$response['message']]);
            }

            return $response;
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
