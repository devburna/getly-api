<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Models\Wallet;
use Illuminate\Support\Facades\Http;

class WalletController extends Controller
{
    private $flutterwaveSecKey;

    public function __construct()
    {
        $this->flutterwaveSecKey = env('FLUTTERWAVE_SEC_KEY');
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWalletRequest  $request
     */
    public function store(StoreWalletRequest $request)
    {
        return Wallet::create($request->only([
            'user_id',
            'reference',
            'identity',
            'bank_name',
            'account_number',
            'account_name'
        ]));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(StoreWalletRequest $request)
    {
        // send request to flutterwave.com
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->post(env('FLUTTERWAVE_URL') . '/virtual-account-numbers', [
            'email' => $request->user()->email_address,
            'is_permanent' => true,
            'bvn' => $request->bvn,
            'tx_ref' => str_shuffle($request->user()->id . config('app.name')),
            'phonenumber' => $request->user()->phone_number,
            'firstname' => $request->user()->first_name,
            'lastname' => $request->user()->last_name,
            'narration' => "{$request->user()->first_name} {$request->user()->last_name}"
        ]);

        if (!$response->ok()) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => "Can't create wallet at the moment.",
            ], 422);
        }

        // new store wallet request instance
        $storeWalletRequest = new StoreWalletRequest($response->json()['data']);

        // set user id
        $storeWalletRequest['user_id'] = $request->user()->id;

        // set account name
        $storeWalletRequest['account_name'] = "{$request->user()->first_name} {$request->user()->last_name}";

        // store wallet
        $this->store($storeWalletRequest);

        return (new UserController())->index($request->user());
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
