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

                // check current provider
                $virtualCard = (new FlutterwaveController())->createVirtualCard($data);

                // store virtual card
                $virtualCard['user_id'] = $request->user()->id;
                $virtualCard['identity'] = $virtualCard['data']['id'];
                $data['meta'] = json_encode($virtualCard);
                $request->user()->virtualCard = $this->store($virtualCard);

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
     * @param  \App\Http\Request  $request
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
     * @param  \App\Http\Requests\UpdateVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateVirtualCardRequest $request)
    {
        try {
            // checks for virtual card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card found for this account.']);
            }

            // toggle virtual card
            $data['action'] = $request->action;
            $data['card'] = $request->user()->virtualCard->identity;
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
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function fund(StoreVirtualCardRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                // checks for virtual card
                if (!$request->user()->virtualCard) {
                    throw ValidationException::withMessages(['No virtual card found for this account.']);
                }

                // checks if sender can fund virtual card
                if (!$request->user()->hasFunds($request->amount)) {
                    throw ValidationException::withMessages([
                        'Insufficient funds, please fund wallet and try again.'
                    ]);
                }

                // fund virtual card
                $data = [];
                $data['card'] = $request->user()->virtualCard->identity;
                $data['amount'] = $request->amount;
                $data['meta'] = "{$request->user()->virtualCard->identity}";

                // checks provider
                $response = match ($request->user()->virtualCard->provider) {
                    'flutterwave' => (new FlutterwaveController())->fundVirtualCard($data),
                    'mono' => (new MonoController())->fundVirtualCard($data),
                };

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
                $transactionRequest['meta'] = json_encode($response);
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
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function withdraw(StoreVirtualCardRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                // checks for virtual card
                if (!$request->user()->virtualCard) {
                    throw ValidationException::withMessages(['No virtual card found for this account.']);
                }


                // withdraw virtual card
                $data = [];
                $data['card'] = $request->user()->virtualCard->identity;
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

    public function transactions(Request $request)
    {
        try {
            // checks for virtual card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card found for this account.']);
            }

            //  virtual card transactions
            $data = [];
            $data['card'] = $request->user()->virtualCard->identity;
            $data['from'] = $request->from;
            $data['to'] = $request->to;
            $data['index'] = $request->index;
            $data['size'] = $request->size;
            $data['page'] = $request->page;

            // checks provider
            $request->user()->virtualCard->transactions = match ($request->user()->virtualCard->provider) {
                'flutterwave' => (new FlutterwaveController())->virtualCardTransactions($data)['data'],
                'mono' => (new FlutterwaveController())->virtualCardTransactions($data)['data']
            };

            return $this->show($request);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }
}
