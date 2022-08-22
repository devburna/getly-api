<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Http\Requests\FundWalletRequest;
use App\Http\Requests\StoreWalletRequest;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * @param  \App\Http\Requests\StoreWalletRequest  $request
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

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\FundWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function fund(FundWalletRequest $request)
    {
        // generate payment link
        $data = [];
        $data['tx_ref'] = Str::uuid();
        $data['name'] = $request->user()->first_name . ' ' . $request->user()->last_name;
        $data['email'] = $request->user()->email_address;
        $data['phone_number'] = $request->user()->phone_number;
        $data['amount'] = $request->amount;
        $data['meta'] = [
            "consumer_id" => $request->user()->wallet->id,
            "consumer_mac" => 'fund-wallet',
        ];

        $link = (new FlutterwaveController())->generatePaymentLink($data)->json()['data'];

        return response()->json([
            'status' => true,
            'data' => [
                'payment_link' => $link['link'],
            ],
            'message' => 'success',
        ]);
    }
}
