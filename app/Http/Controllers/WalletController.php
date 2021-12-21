<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\WalletDepositRequest;
use App\Http\Requests\WalletWithdrawRequest;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
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
            $request['user_id'] = $request->user()->id;
            $request['amount'] = $request->amount;
            $request['provider'] = 'flutterwave';
            $request['channel'] = 'deposit';
            $request['spent'] = false;
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

    public function withdraw(WalletWithdrawRequest $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'Payment sent',
        ]);
    }

    public function update(Request $request, $email, $type = null)
    {
        if ($user = User::where('email', $email)->first()) {
            return DB::transaction(function () use ($request, $user, $type) {
                switch ($type) {
                    case 'credit':
                        $user->wallet->update([
                            'balance' => $user->wallet->balance + $request->amount,
                        ]);

                        $spent = false;
                        break;

                    default:

                        $user->wallet->update([
                            'balance' => $user->wallet->balance - $request->amount,
                        ]);

                        $spent = true;
                        break;
                }

                $request['user_id'] = $user->id;
                $request['amount'] = $request->amount;
                $request['provider'] = 'getly';
                $request['channel'] = 'gift';
                $request['summary'] = $request['summary'];
                $request['reference'] = (string) Str::uuid();
                $request['spent'] = $spent;
                $request['status'] = TransactionType::Success();
                $request['method'] = 'wallet';

                (new TransactionController())->store($request);
            });
        }
    }
}
