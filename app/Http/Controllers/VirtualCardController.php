<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVirtualCardRequest;
use App\Http\Requests\UpdateVirtualCardRequest;
use App\Models\VirtualCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class VirtualCardController extends Controller
{
    private $flutterwaveSecKey;

    public function __construct()
    {
        $this->flutterwaveSecKey = env('FLUTTERWAVE_SEC_KEY');
    }
    /**
     * Show the form for creating a new resource.
     *
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function create(StoreVirtualCardRequest $request)
    {
        // checks duplicate wallet
        if ($request->user()->virtualCard) {
            return $this->show($request);
        }

        // checks if sender can fund virtual card
        if ($request->amount > $request->user()->wallet->current_balance) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient funds, please fund wallet and try again.'
            ]);
        }

        // send request to flutterwave.com
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->flutterwaveSecKey}",
        ])->post(env('FLUTTERWAVE_URL') . '/virtual-cards', [
            'currency' => 'USD',
            'amount' => 5,
            'billing_name' => "{$request->user()->first_name} {$request->user()->last_name}",
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

        // set virtual card data
        $data = $response->json()['data'];
        $data['user_id'] = $request->user()->id;
        $data['identity'] = $data['id'];
        $data['provider'] = 'flutterwave';

        // new request instance
        $storeVirtualCardRequest = new StoreVirtualCardRequest($data);

        // store virtual account
        $request->user()->virtualCard = $this->store($storeVirtualCardRequest);

        return $this->show($request);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     */
    public function store(StoreVirtualCardRequest $request)
    {
        return VirtualCard::create($request->only([
            'user_id',
            'identity',
            'account_id',
            'currency',
            'card_hash',
            'card_pan',
            'masked_pan',
            'name_on_card',
            'expiration',
            'cvv',
            'address_1',
            'address_2',
            'city',
            'state',
            'zip_code',
            'callback_url',
            'is_active',
            'provider',
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

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateVirtualCardRequest  $request
     * @param  \App\Models\VirtualCard  $virtualCard
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateVirtualCardRequest $request, VirtualCard $virtualCard)
    {
        //
    }
}
