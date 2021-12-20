<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\WalletDepositRequest;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function deposit(WalletDepositRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $request['user_id'] = $request->user()->id;
            $request['amount'] = $request->amount;
            $request['provider'] = 'flutterwave';
            $request['channel'] = 'deposit';
            $request['description'] = 'Deposit';
            $request['reference'] = (string) Str::uuid();
            $request['redirect_url'] = route('verify-payment');
            $request['status'] = TransactionType::Pending();

            switch (env('PAYMENT_PROVIDER')) {
                case 'glade':
                    $link = (new GladeController())->generatePaymentLink($request);
                    break;

                default:
                    # code...
                    $link = (new FWController())->generatePaymentLink($request);
                    break;
            }

            switch ($link['status']) {
                case 'success':
                    // create transaction
                    (new TransactionController())->store($request);
                    break;

                default:
                    # code...
                    break;
            }

            return response()->json($link);
        });
    }
}
