<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonoAccountHolderRequest;
use App\Http\Requests\StoreVirtualCardRequest;
use App\Models\VirtualCard;
use App\Notifications\VirtualCardTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VirtualCardController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function create(StoreVirtualCardRequest $request)
    {
        try {

            // create a mono account if not found
            if (!$request->user()->virtualCard || !$request->user()->monoAccount) {

                // re-verify bvn
                $bvn = (new MonoController())->verifyBvn($request->bvn);

                $storeMonoAccountHolderRequest = (new StoreMonoAccountHolderRequest());
                $storeMonoAccountHolderRequest['user_id'] = $request->user()->id;
                $storeMonoAccountHolderRequest['first_name'] = $bvn['data']['first_name'];
                $storeMonoAccountHolderRequest['last_name'] = $bvn['data']['last_name'];
                $storeMonoAccountHolderRequest['bvn'] = $request->bvn;
                $storeMonoAccountHolderRequest['phone'] = $bvn['data']['phone'];
                (new MonoAccountHolderController())->createAccountHolder($storeMonoAccountHolderRequest);
            }

            // generate virtual card
            $virtualCard = (new MonoController())->createVirtualCard($request->user()->monoAccountHolder->identity);
            $request['user_id'] = $request->user()->id;
            $request['identity'] = $virtualCard['data']['id'];
            $request['provider'] = $virtualCard['data']['provider'];
            $request['meta'] = json_encode($virtualCard);

            // store vitual account
            $request->user()->virtualCard = $this->store($request);

            return $this->show($request);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage()
            ], 422);
        }
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
            'provider',
            'meta'
        ]));
    }

    /**
     * Display the specified resource.
     *
     * @param Illuminate\Http\Request;
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        try {
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card created for this account.']);
            }

            // get virtual card details
            $virtualCard = (new MonoController())->virtualCardDetails($request->user()->virtualCard->identity);

            return response()->json([
                'status' => true,
                'data' => $virtualCard['data'],
                'message' => 'success',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage()
            ], 422);
        }
    }

    public function fund(Request $request)
    {
        try {
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card created for this account.']);
            }

            // fund virtual card
            $virtualCard = (new MonoController())->virtualCardDetails($request->user()->virtualCard->identity);

            return response()->json([
                'status' => true,
                'data' => $virtualCard['data'],
                'message' => 'success',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage()
            ], 422);
        }
    }

    public function transactionReceived($data)
    {
        try {
            if (!$virtualCard = VirtualCard::where('identity', $data['data']['card'])->first()) {
                return response()->json([], 401);
            }

            // notify user of transaction
            $virtualCard->user->notify(new VirtualCardTransaction($data['data']));

            return response()->json([]);
        } catch (\Throwable $th) {
            return response()->json([], 422);
        }
    }
}
