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

    public function createVirtualCard($name, $amount)
    {
        $card = Http::withHeaders([
            'content-type' => 'application/json',
            'key' => $this->key,
            'mid' => $this->mid,
        ])->put('https://api.glade.ng/virtualcard', [
            'action' => 'create',
            'billing' => [
                'address' => '333 Fremont Road',
                'name' => $name,
                'city' => 'San Fransisco',
                'state' => 'California',
                'postal_code' => '94124',
            ],
            'amount' => $amount,
            'currency' => 'NGN',
            'country' => 'NG',
        ])->json();

        if ($card['status'] = 200) {
            if ($card['message'] === 'Created Successfully') {
                $card['provider'] = 'glade';
                return $card;
            }
        }
        return null;
    }

    public function fundVirtualCard($amount, $card)
    {
        $card = Http::withHeaders([
            'content-type' => 'application/json',
            'key' => $this->key,
            'mid' => $this->mid,
        ])->put('https://api.glade.ng/virtualcard', [
            'action' => 'fund',
            'reference' => $card,
            'amount' => $amount,
        ])->json();

        if ($card['status'] = 200) {
            if ($card['message'] === 'Withdrawal Successfully') {
                return true;
            }
        }

        return false;
    }

    public function withdrawVirtualCard($amount, $card)
    {
        $card = Http::withHeaders([
            'content-type' => 'application/json',
            'key' => $this->key,
            'mid' => $this->mid,
        ])->put('https://api.glade.ng/virtualcard', [
            'action' => 'withdraw',
            'reference' => $card,
            'amount' => $amount,
        ])->json();

        if ($card['status'] = 200) {
            if ($card['message'] === 'Withdrawal Successfully') {
                return true;
            }
        }
        return false;
    }

    public function virtualCardTrx($card)
    {
        $card = Http::withHeaders([
            'content-type' => 'application/json',
            'key' => $this->key,
            'mid' => $this->mid,
        ])->put('https://api.glade.ng/virtualcard', [
            'action' => 'transactions',
            'reference' => $card,
        ])->json();

        return $card;
    }

    public function virtualCardDetails($card)
    {
        $card = Http::withHeaders([
            'content-type' => 'application/json',
            'key' => $this->key,
            'mid' => $this->mid,
        ])->put('https://api.glade.ng/virtualcard', [
            'action' => 'get',
            'reference' => $card,
        ])->json();

        return $card;
    }
}
