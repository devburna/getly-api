<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreVirtualCardRequest;
use App\Http\Requests\UpdateVirtualCardRequest;
use App\Models\VirtualCard;
use App\Notifications\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                    return $this->show($request->user()->virtualCard);
                }

                // checks if sender can fund virtual card
                if (!$request->user()->hasFunds($request->amount)) {
                    throw ValidationException::withMessages([
                        'Insufficient funds, please fund wallet and try again.'
                    ]);
                }

                // generate virtual card
                $data = [];
                $data['amount'] = $request->amount;
                $data['first_name'] = $request->user()->first_name;
                $data['last_name'] = $request->user()->last_name;

                $virtualCard = (new FlutterwaveController())->createVirtualCard($data);

                // store virtual card
                $request['user_id'] = $request->user()->id;
                $request['identity'] = $virtualCard['id'];
                $request['provider'] = 'flutterwave';
                $request->user()->virtualCard = $this->store($request);

                // debit user wallet
                $request->user()->debit($request->amount);

                // store transaction
                $transactionRequest = new StoreTransactionRequest();
                $transactionRequest['user_id'] = $request->user()->id;
                $transactionRequest['identity'] = Str::uuid();
                $transactionRequest['reference'] = Str::uuid();
                $transactionRequest['type'] = TransactionType::DEBIT();
                $transactionRequest['channel'] = TransactionChannel::WALLET();
                $transactionRequest['amount'] = $request->amount;
                $transactionRequest['narration'] = 'New virtual card';
                $transactionRequest['status'] = TransactionStatus::SUCCESS();
                $transaction = (new TransactionController())->store($transactionRequest);

                // notify user of transaction
                $request->user()->notify(new Transaction($transaction));

                return $this->show($request, 'success', 201);
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
        return response()->json([
            'status' => true,
            'data' => $request->user()->virtualCard,
            'message' => $message,
        ], $code);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Models\VirtualCard  $virtualCard
     * @param  \App\Http\Requests\UpdateVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function update(VirtualCard $virtualCard, UpdateVirtualCardRequest $request)
    {
        try {
            // toggle virtual card
            $data['action'] = $request->action;
            $data['card'] = $virtualCard->identity;
            (new FlutterwaveController())->withdrawVirtualCard($data);

            return $this->show($request);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Models\VirtualCard  $virtualCard
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function fund(VirtualCard $virtualCard, StoreVirtualCardRequest $request)
    {
        try {
            return DB::transaction(function () use ($request, $virtualCard) {
                // checks if sender can fund virtual card
                if (!$request->user()->hasFunds($request->amount)) {
                    throw ValidationException::withMessages([
                        'Insufficient funds, please fund wallet and try again.'
                    ]);
                }

                // fund virtual card
                $data = [];
                $data['card'] = $virtualCard->identity;
                $data['amount'] = $request->amount;
                (new FlutterwaveController())->fundVirtualCard($data);

                // debit user wallet
                $request->user()->debit($request->amount);

                // store transaction
                $transactionRequest = new StoreTransactionRequest();
                $transactionRequest['user_id'] = $request->user()->id;
                $transactionRequest['identity'] = Str::uuid();
                $transactionRequest['reference'] = Str::uuid();
                $transactionRequest['type'] = TransactionType::DEBIT();
                $transactionRequest['channel'] = TransactionChannel::WALLET();
                $transactionRequest['amount'] = $request->amount;
                $transactionRequest['narration'] = 'Virtual card top up';
                $transactionRequest['status'] = TransactionStatus::SUCCESS();
                $transaction = (new TransactionController())->store($transactionRequest);

                // notify user of transaction
                $request->user()->notify(new Transaction($transaction));

                return $this->show($request);
            });
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Models\VirtualCard  $virtualCard
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function withdraw(VirtualCard $virtualCard, StoreVirtualCardRequest $request)
    {
        try {
            // checks if user has virtaul card
            return DB::transaction(function () use ($request, $virtualCard) {

                // withdraw virtual card
                $data = [];
                $data['card'] = $virtualCard->identity;
                $data['amount'] = $request->amount;
                (new FlutterwaveController())->withdrawVirtualCard($data);

                // credit user wallet
                $request->user()->credit($request->amount);

                // store transaction
                $transactionRequest = new StoreTransactionRequest();
                $transactionRequest['user_id'] = $request->user()->id;
                $transactionRequest['identity'] = Str::uuid();
                $transactionRequest['reference'] = Str::uuid();
                $transactionRequest['type'] = TransactionType::CREDIT();
                $transactionRequest['channel'] = TransactionChannel::VIRTUAL_CARD();
                $transactionRequest['amount'] = $request->amount;
                $transactionRequest['narration'] = 'Virtual card withdrawal';
                $transactionRequest['status'] = TransactionStatus::SUCCESS();
                $transaction = (new TransactionController())->store($transactionRequest);

                // notify user of transaction
                $request->user()->notify(new Transaction($transaction));

                return $this->show($request);
            });
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    public function transactions(Request $request, VirtualCard $virtualCard)
    {
        try {

            //  virtual card transactions
            $data = [];
            $data['card'] = $virtualCard->identity;
            $data['from'] = $request->from;
            $data['to'] = $request->to;
            $data['index'] = $request->index;
            $data['size'] = $request->size;
            $virtualCard->transactions = (new FlutterwaveController())->virtualCardTransactions($data);

            return $this->show($request);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }
}
