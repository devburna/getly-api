<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Models\Wallet;
use Illuminate\Http\Request;

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
    public function show(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => $request->user()->wallet,
            'message' => 'success',
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function withdraw(StoreWalletRequest $request)
    {
        return response()->json([
            'status' => true,
            'data' => $request->all(),
            'message' => 'success',
        ]);
    }
}
