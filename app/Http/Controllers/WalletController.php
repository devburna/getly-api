<?php

namespace App\Http\Controllers;

use App\Enums\WalletUpdateType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        Wallet::updateOrCreate($request->only('user_id', 'balance'));
    }

    /**
     * Display the specified resource.
     *
     * @param  Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $wallet = $request->user()->wallet;
        $wallet->currency = 'USD';
        $wallet->symbol = '$';

        return response()->json([
            'status' => true,
            'data' =>  $wallet,
            'message' => 'Fetched'
        ]);
    }

    public function update(User $user, $amount, $type)
    {
        switch ($type) {
            case WalletUpdateType::Credit():
                $balance = $user->wallet->balance + $amount;
                # code...
                break;

            default:
                $balance = $user->wallet->balance - $amount;
                # code...
                break;
        }

        $user->wallet->update([
            'balance' => $balance,
        ]);
    }
}
