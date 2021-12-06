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
     * @return \Illuminate\Http\Response
     */
    public function store($user)
    {
        $v_card = (new FWController())->create();

        Wallet::create([
            'user_id' => $user,
            'identifier' => $v_card['data']['id'],
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $wallet = (new FWController())->card($request->user()->wallet->identifier);

        return response()->json([
            'status' => true,
            'data' =>  $wallet,
            'message' => 'Fetched'
        ]);
    }
}
