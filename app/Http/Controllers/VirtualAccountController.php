<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVirtualAccountRequest;
use App\Models\VirtualAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VirtualAccountController extends Controller
{
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

        // get bvn info
        $data = [];
        $data['bvn'] = $request->identity;
        $bvn = (new FlutterwaveController())->verifyBvn($data);

        // check if not status 200
        if (!$bvn->ok()) {
            throw ValidationException::withMessages([
                'bvn' => $bvn['message']
            ]);
        }

        // generate virtual card
        $bvn = $bvn->json()['data'];
        $data = [];
        $request['id'] = $request->user()->id;
        $data['bvn'] = $bvn['bvn'];
        $data['first_name'] = $bvn['first_name'];
        $data['last_name'] = $bvn['last_name'];
        $data['email_address'] = $request->user()->email_address;
        $data['phone_number'] = $bvn['phone_number'];

        $virtualAccount = (new FlutterwaveController())->createVirtualAccount($data);

        // catch error
        if (!$virtualAccount->ok()) {
            throw ValidationException::withMessages([
                'message' => $bvn['message']
            ]);
        }

        // set user id
        $request['user_id'] = $request->user()->id;

        // set virtual account data
        $data = $virtualAccount->json()['data'];
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
