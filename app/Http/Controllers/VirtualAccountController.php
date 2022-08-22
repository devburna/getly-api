<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVirtualAccountRequest;
use App\Models\VirtualAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VirtualAccountController extends Controller
{
    private $flutterwaveSecKey;

    public function __construct()
    {
        $this->flutterwaveSecKey = env('FLUTTERWAVE_SEC_KEY');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  \App\Http\Requests\StoreVirtualAccountRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function create(StoreVirtualAccountRequest $request)
    {
        // checks if bvn was approved
        if (!$request->approved) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => 'Please verify your BVN.',
            ], 422);
        }

        // checks duplicate wallet
        if ($request->user()->virtualAccount) {
            return $this->show($request);
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

        // set virtual account data
        $data = $response->json()['data'];
        $data['user_id'] = $request->user()->id;
        $data['identity'] = $data['order_ref'];
        $data['account_name'] = "{$request->user()->first_name} {$request->user()->last_name}";
        $data['provider'] = 'flutterwave';

        // new request instance
        $storeVirtualAccountRequest = new StoreVirtualAccountRequest($data);

        // store virtual account
        $request->user()->virtualAccount = $this->store($storeVirtualAccountRequest);

        return $this->show($request);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreVirtualAccountRequest  $request
     */
    public function store(StoreVirtualAccountRequest $request)
    {
        return VirtualAccount::create($request->only([
            'user_id',
            'identity',
            'bank_name',
            'account_number',
            'account_name',
            'provider'
        ]));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Http\Requests  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => $request->user()->virtualAccount,
            'message' => 'success',
        ]);
    }
}
