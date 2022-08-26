<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\FundWalletRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\WithdrawWalletRequest;
use App\Models\Transaction as ModelsTransaction;
use App\Models\Wallet;
use App\Notifications\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWalletRequest  $request
     */
    public function store(StoreWalletRequest $request)
    {
        // set user id
        $request['user_id'] = $request[0]->id;
        $request['currency'] = 'Nigerian Naira';
        $request['short'] = 'NGN';
        $request['symbol'] = '₦';

        return Wallet::create($request->only([
            'user_id',
            'currency',
            'short',
            'symbol',
        ]));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Http\Requests\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $message = 'success', $code = 200)
    {
        return response()->json([
            'status' => true,
            'data' => $request->user()->wallet,
            'message' => $message,
        ], $code);
    }

    public function withdraw(Request $request)
    {
        try {

            if (!$request->user()->virtualAccount) {
                throw ValidationException::withMessages(['No virtual account created for this account.']);
            }

            // get virtual account details
            $virtualAccount = (new MonoController())->virtualAccountTransfer($request->user()->virtualAccount->identity);

            // clean response data
            unset($virtualAccount['data']['id']);
            unset($virtualAccount['data']['budget']);
            unset($virtualAccount['data']['type']);
            unset($virtualAccount['data']['bank_code']);
            unset($virtualAccount['data']['currency']);
            unset($virtualAccount['data']['balance']);
            unset($virtualAccount['data']['created_at']);
            unset($virtualAccount['data']['updated_at']);
            unset($virtualAccount['data']['account_holder']);

            return response()->json([
                'status' => true,
                'data' => $virtualAccount['data'],
                'message' => 'success',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage()
            ], 422);
        }
    }
}
