<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Models\Wallet;

class WalletController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWalletRequest $request)
    {
        return Wallet::create([
            'user_id' => $request->user_id,
            'currency' => 'Nigerian Naira',
            'short' => 'NGN',
            'symbol' => '₦'
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function fund(StoreWalletRequest $request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function withdraw(StoreWalletRequest $request)
    {
        //
    }
}
