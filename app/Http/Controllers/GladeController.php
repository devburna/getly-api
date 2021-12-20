<?php

namespace App\Http\Controllers;

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

    public function generatePaymentLink(Request $request)
    {
        
    }

    public function createVirtualCard(Request $request)
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
                    'name' => $request->name,
                    'city' => 'San Fransisco',
                    'state' => 'California',
                    'postal_code' => '94124',
                ],
                'amount' => $request->amount,
                'currency' => 'NGN',
                'country' => 'NG',
            ])->json();

            if ($card['status'] = 200) {
                if ($card['message'] === 'Created Successfully') {

                    $request['user_id'] = $request->user()->id;
                    $request['reference'] = $card['card_data']['reference'];
                    $request['provider'] = 'glade';

                    (new VirtualCardController())->store($request);
                }
                return $card;
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function fundVirtualCard($reference, $amount)
    {
        try {
            $card = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->put('https://api.glade.ng/virtualcard', [
                'action' => 'fund',
                'reference' => $reference,
                'amount' => $amount,
            ]);

            if ($card->ok()) {
                return $card->json();
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function withdrawVirtualCard(Request $request, $reference, $amount)
    {
        try {
            $card = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->put('https://api.glade.ng/virtualcard', [
                'action' => 'withdraw',
                'reference' => $reference,
                'amount' => $amount,
            ])->json();

            if ($card['status'] = 200) {
                if ($card['message'] === 'Withdrawal Successfully') {
                    return $this->moneyTransfer($request);
                }
                return $card;
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function virtualCardTrx($reference)
    {
        try {
            $card = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->put('https://api.glade.ng/virtualcard', [
                'action' => 'transactions',
                'reference' => $reference,
            ]);

            if ($card->ok()) {
                return $card->json();
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function virtualCardDetails($reference)
    {
        try {
            $card = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->put('https://api.glade.ng/virtualcard', [
                'action' => 'get',
                'reference' => $reference,
            ]);

            if ($card->ok()) {
                return $card->json();
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function moneyTransfer(Request $request)
    {
        try {
            $transfer = Http::withHeaders([
                'content-type' => 'application/json',
                'key' => $this->key,
                'mid' => $this->mid,
            ])->post('https://demo.api.glade.ng/disburse', [
                'action' => 'transfer',
                'amount' => $request->amount,
                'accountnumber' => $request->accountnumber,
                'sender_name' => $request->sender_name,
                'narration' => $request->narration,
                'orderRef' => 'TX' . time(),
            ]);

            if ($transfer->ok()) {
                return $transfer->json();
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
