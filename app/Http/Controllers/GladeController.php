<?php

namespace App\Http\Controllers;

use App\Http\Requests\VirtualCardRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GladeController extends Controller
{
    public $key, $mid;

    public function __construct()
    {
        $this->key =  env('GLADE_MERCHANT_KEY');
        $this->mid =  env('GLADE_MERCHANT_ID');
    }

    public function createVirtualCard(VirtualCardRequest $request)
    {
        try {
            $card = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->put('https://api.glade.ng/virtualcard', [
                'action' => 'create',
                'billing' => [
                    'address' => '333 Fremont Road',
                    'name' => $request->user()->name,
                    'city' => 'San Fransisco',
                    'state' => 'California',
                    'postal_code' => '94124',
                ],
                'amount' => $request->amount,
                'currency' => 'USD',
                'country' => 'US',
            ])->json();

            if ($card['status'] = 200) {
                if ($card['message'] === 'Created Successfully') {

                    $request['user_id'] = $request->user()->id;
                    $request['reference'] = $card['card_data']['reference'];
                    $request['provider'] = 'glade';

                    (new VirtualCardController())->store($request);
                }
            }
            return $card;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function fundVirtualCard(VirtualCardRequest $request)
    {
        try {
            $card = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->put('https://api.glade.ng/virtualcard', [
                'action' => 'fund',
                'reference' => $request->reference,
                'amount' => $request->amount,
            ])->json();

            if ($card['status'] = 200) {
                if ($card['message'] === 'Withdrawal Successfully') {
                    $request['amount'] =  $request->amount;
                    $request['summary'] = 'Virtual card topup';
                    (new WalletController())->update($request, $request->user()->email, 'debit');
                }
            }

            return $card;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function withdrawVirtualCard(Request $request)
    {
        try {
            $card = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->put('https://api.glade.ng/virtualcard', [
                'action' => 'withdraw',
                'reference' => $request->reference,
                'amount' => $request->amount,
            ])->json();

            if ($card['status'] = 200) {
                if ($card['message'] === 'Withdrawal Successfully') {
                    $request['amount'] =  $request->amount;
                    $request['summary'] = 'Virtual card withdrawal';
                    (new WalletController())->update($request, $request->user()->email, 'credit');
                }
            }
            return $card;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function virtualCardTrx(Request $request)
    {
        try {
            $card = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->put('https://api.glade.ng/virtualcard', [
                'action' => 'transactions',
                'reference' => $request->reference,
            ])->json();

            return $card;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function virtualCardDetails(Request $request)
    {
        try {
            $card = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->put('https://api.glade.ng/virtualcard', [
                'action' => 'get',
                'reference' => $request->reference,
            ])->json();

            return $card;
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
