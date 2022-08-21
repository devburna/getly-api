<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Models\Wallet;
use Illuminate\Http\Request;
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
            'identity',
            'provider'
        ]));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(StoreWalletRequest $request)
    {
        if ($request->user()->wallet) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Please contact support.',
            ], 405);
        }

        // send request to flutterwave.com
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->post(env('FLUTTERWAVE_URL') . '/virtual-account-numbers', [
            'email' => $request->user()->email_address,
            'is_permanent' => true,
            'bvn' => 22418085857,
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
                'message' => $response->json(),
            ], 422);
        }

        // set user id
        $request['user_id'] = $request->user()->id;

        // set wallet data
        $request['identity'] = $response->json()['data']['order_ref'];
        $request['provider'] = 'flutterwave';

        // store wallet
        $request->user()->wallet = $this->store($request);

        return $this->show($request);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Http\Requests\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        // send request to flutterwave.com
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->get(env('FLUTTERWAVE_URL') . "/virtual-account-numbers/{$request->user()->wallet->identity}");

        if (!$response->ok()) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $response->json(),
            ], 422);
        }

        // set data
        $data = $response->json()['data'];
        $data['account_name'] = "{$request->user()->first_name} {$request->user()->last_name}";
        unset($data['response_code']);
        unset($data['order_ref']);
        unset($data['response_message']);

        return response()->json([
            'status' => true,
            'data' => $data,
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
