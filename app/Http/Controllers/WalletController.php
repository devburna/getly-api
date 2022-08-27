<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\WithdrawWalletRequest;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function transfer(WithdrawWalletRequest $request)
    {
        try {

            return DB::transaction(function () use ($request) {
                if (!$request->user()->virtualAccount) {
                    throw ValidationException::withMessages(['Transfer not available at the moment.']);
                }

                // if user has funds
                if (!$request->user()->hasFunds($request->amount)) {
                    throw ValidationException::withMessages([
                        'Insufficient funds, please fund wallet and try again.'
                    ]);
                }

                // transfer with mono
                $request['cust'] = $request->user()->virtualAccount->identity;
                (new MonoController())->virtualAccountTransfer($request->all());

                // debit user wallet
                $request->user()->debit($request->amount);

                return response()->json([
                    'status' => true,
                    'data' => [
                        'amount' => $request->amount
                    ],
                    'message' => 'success',
                ]);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage()
            ], 422);
        }
    }
}
