<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVirtualCardRequest;
use App\Http\Requests\UpdateVirtualCardRequest;
use App\Models\VirtualCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            return DB::transaction(function () use ($request) {

                // checks duplicate wallet
                if ($request->user()->virtualCard) {
                    return $this->show($request);
                }

                // checks if sender can fund virtual card
                if (!$request->user()->hasFunds($request->amount)) {
                    throw ValidationException::withMessages([
                        'amount' => 'Insufficient funds, please fund wallet and try again.'
                    ]);
                }

                // generate virtual card
                $data = [];
                $data['amount'] = $request->amount;
                $data['first_name'] = $request->user()->first_name;
                $data['last_name'] = $request->user()->last_name;

                $virtualCard = (new FlutterwaveController())->createVirtualCard($data);

                // set user id
                $request['user_id'] = $request->user()->id;

                // set virtual card data
                $data = $virtualCard->json()['data'];
                $data['user_id'] = $request->user()->id;
                $data['identity'] = $data['id'];
                $data['provider'] = 'flutterwave';

                // new request instance
                $storeVirtualCardRequest = new StoreVirtualCardRequest($data);

                // store virtual card
                $request->user()->virtualCard = $this->store($storeVirtualCardRequest);

                // debit user wallet
                $request->user()->debit($request->amount);

                return $this->show($request, $virtualCard->json()['message'], 201);
            });
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
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
    public function show(Request $request, $message = 'success', $code = 200)
    {
        // checks if user has virtaul card
        if (!$request->user()->virtualCard) {
            throw ValidationException::withMessages([
                'message' => 'No virtual card found for this account.',
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $request->user()->virtualCard,
            'message' => $message,
        ], $code);
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
        try {
            // checks if user has virtaul card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages([
                    'message' => 'No virtual card found for this account.',
                ]);
            }

            // toggle virtual card
            $data['action'] = $request->action;

            $virtualCard = (new FlutterwaveController())->withdrawVirtualCard($data);

            return $this->show($request, $virtualCard->json()['message']);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    public function fund(StoreVirtualCardRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                // checks if sender can fund virtual card
                if (!$request->user()->hasFunds($request->amount)) {
                    throw ValidationException::withMessages([
                        'amount' => 'Insufficient funds, please fund wallet and try again.'
                    ]);
                }

                // checks if user has virtaul card
                if (!$request->user()->virtualCard) {
                    throw ValidationException::withMessages([
                        'message' => 'No virtual card found for this account.',
                    ]);
                }

                // fund virtual card
                $data = [];
                $data['card'] = $request->user()->virtualCard->identity;
                $data['amount'] = $request->amount;

                $virtualCard = (new FlutterwaveController())->fundVirtualCard($data);

                // debit user wallet
                $request->user()->debit($request->amount);

                return $this->show($request, $virtualCard->json()['message']);
            });
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    public function withdraw(StoreVirtualCardRequest $request)
    {
        try {
            // checks if user has virtaul card
            return DB::transaction(function () use ($request) {
                if (!$request->user()->virtualCard) {
                    throw ValidationException::withMessages([
                        'message' => 'No virtual card found for this account.',
                    ]);
                }

                // withdraw virtual card
                $data = [];
                $data['card'] = $request->user()->virtualCard->identity;
                $data['amount'] = $request->amount;

                $virtualCard = (new FlutterwaveController())->withdrawVirtualCard($data);

                // credit user wallet
                $request->user()->credit($request->amount);

                return $this->show($request, $virtualCard->json()['message']);
            });
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    public function transactions(Request $request)
    {
        try {
            // checks if user has virtaul card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages([
                    'message' => 'No virtual card found for this account.',
                ]);
            }

            //  virtual card transactions
            $data = [];
            $data['from'] = $request->from;
            $data['to'] = $request->to;
            $data['index'] = $request->index;
            $data['size'] = $request->size;

            $virtualCard = (new FlutterwaveController())->virtualCardTransactions($data);

            return response()->json([
                'status' => true,
                'data' => $virtualCard->json()['data'],
                'message' => $virtualCard->json()['message'],
            ]);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }
}
