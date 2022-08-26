<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\FundVirtualCardRequest;
use App\Http\Requests\StoreMonoAccountHolderRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreVirtualCardRequest;
use App\Http\Requests\ToggleVirtualCardRequest;
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

    public function fund(FundVirtualCardRequest $request)
    {
        try {
            // if user has virtual card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card created for this account.']);
            }

            // if user has fund
            if ($request->user()->hasFunds($request->amount)) {
                throw ValidationException::withMessages([
                    'Insufficient funds, please fund wallet and try again.'
                ]);
            }

            // fund virtual card
            $request['card'] = $request->user()->virtualCard->identity;
            $request['amount'] = $request->amount;
            $virtualCard = (new MonoController())->virtualCardDetails($request->all());

            // create transaction
            $storeTransactionRequest = (new StoreTransactionRequest());
            $storeTransactionRequest['user_id'] = $request->user()->id;
            $storeTransactionRequest['identity'] = str_shuffle($request->user()->virtualCard->identity . time());
            $storeTransactionRequest['reference'] = str_shuffle($request->user()->virtualCard->identity);
            $storeTransactionRequest['type'] = TransactionType::DEBIT();
            $storeTransactionRequest['channel'] = TransactionChannel::WALLET();
            $storeTransactionRequest['amount'] = $request->amount;
            $storeTransactionRequest['narration'] = 'Virtual card funding';
            $storeTransactionRequest['status'] = TransactionStatus::SUCCESS();
            $storeTransactionRequest['meta'] = json_encode($virtualCard);
            $transaction = (new TransactionController())->store($storeTransactionRequest);

            // notify user of transaction
            $transaction->user->notify(new VirtualCardTransaction($transaction));

            return response()->json([
                'status' => true,
                'data' => [
                    'amount' => $request->amount,
                ],
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

    public function transactions(Request $request)
    {
        try {
            // if user has virtual card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card created for this account.']);
            }

            // get virtual card transactions
            $request['card'] = $request->user()->virtualCard->identity;
            $request['page'] = $request->page;
            $request['from'] = $request->from;
            $request['to'] = $request->to;
            $virtualCard = (new MonoController())->virtualCardTransactions($request->all());

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

    public function toggle(ToggleVirtualCardRequest $request)
    {
        try {
            // if user has virtual card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card created for this account.']);
            }

            // get virtual card transactions
            $request['card'] = $request->user()->virtualCard->identity;
            $request['action'] = $request->action;
            (new MonoController())->virtualCardTransactions($request->all());

            return response()->json([
                'status' => true,
                'data' => [
                    'action' =>  $request->action
                ],
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
