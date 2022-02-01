<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\WalletDepositRequest;
use App\Http\Requests\WalletWithdrawRequest;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($request) {
            $link = (new FWController())->generatePaymentLink($request->amount, $request->user()->name, $request->user()->email, $request->user()->profile->phone, "Wallet deposit");

            if (!$link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Error processing request, please contact support'
                ], 422);
            } else {
                (new TransactionController())->store([
                    'user_id' => $request->user()->id,
                    'reference' => $link['data']['reference'],
                    'provider' => 'flutterwave',
                    'channel' => 'deposit',
                    'amount' => $request->amount,
                    'summary' => 'Wallet deposit',
                    'spent' => false,
                    'status' => TransactionType::Pending(),
                ]);

                return response()->json([
                    'status' => true,
                    'data' => [
                        'link' => $link['data']['link']
                    ],
                    'message' => $link['message'],
                ]);
            }
        });
    }

    public function withdraw(WalletWithdrawRequest $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'Payment sent',
        ]);
    }
}
