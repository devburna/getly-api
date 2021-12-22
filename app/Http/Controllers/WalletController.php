<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\WalletDepositRequest;
use App\Http\Requests\WalletWithdrawRequest;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public $reference;

    public function __construct()
    {
        $this->reference = str_shuffle(time() . mt_rand(1000, 9999));
    }
    
    public function create(Request $request)
    {
        Wallet::create($request->only('user_id'));
    }

    public function details(Request $request)
    {
        return response()->json([
            'status' => false,
            'data' => $request->user()->wallet,
            'message' => 'Fetched',
        ]);
    }

    public function deposit(WalletDepositRequest $request)
    {
        $request['user_id'] = $request->user()->id;
        $request['amount'] = $request->amount;
        $request['provider'] = 'flutterwave';
        $request['channel'] = 'deposit';
        $request['spent'] = false;
        $request['description'] = 'Deposit';
        $request['reference'] = (string) Str::uuid();
        $request['redirect_url'] = route('verify-payment');
        $request['status'] = TransactionType::Pending();

        $link = (new FWController())->generatePaymentLink([
            'reference' => $this->reference,
            'amount' => $request->amount,
            'email' => $request->user()->email,
            'phone' => $request->user()->profile->phone,
            'name' => $request->user()->name,
            'description' => 'Deposit',
        ]);

        if (!$link) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing request, please contact support'
            ], 422);
        }

        (new TransactionController())->store([
            'user_id' => $request->user()->id,
            'reference' => $this->reference,
            'provider' => 'flutterwave',
            'channel' => 'deposit',
            'amount' => $request->amount,
            'summary' => 'Wallet deposit',
            'spent' => false,
            'status' => TransactionType::Pending(),
        ]);

        return response()->json([
            'status' => true,
            'data' => $link,
            'message' => 'Complete payment'
        ]);
    }

    public function withdraw(WalletWithdrawRequest $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'Payment sent',
        ]);
    }
}
