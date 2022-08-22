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
        try {
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
            $bvn = (new FlutterwaveController())->verifyBvn($request->identity)->json()['data'];

            // generate virtual card
            $bvn['id'] = $request->user()->id;
            $bvn['bvn'] = $bvn['bvn'];
            $bvn['first_name'] = $bvn['first_name'];
            $bvn['last_name'] = $bvn['last_name'];
            $bvn['email_address'] = $request->user()->email_address;
            $bvn['phone_number'] = $bvn['phone_number'];

            $virtualAccount = (new FlutterwaveController())->createVirtualAccount($bvn)->json()['data'];

            // set user id
            $request['user_id'] = $request->user()->id;

            // set virtual account data
            $virtualAccount['user_id'] = $request->user()->id;
            $virtualAccount['identity'] = $virtualAccount['order_ref'];
            $virtualAccount['account_name'] = "{$request->user()->first_name} {$request->user()->last_name}";
            $virtualAccount['provider'] = 'flutterwave';

            // new request instance
            $storeVirtualAccountRequest = new StoreVirtualAccountRequest($virtualAccount);

            // store virtual account
            $request->user()->virtualAccount = $this->store($storeVirtualAccountRequest);

            return $this->show($request);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
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
