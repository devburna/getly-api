<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\FundWalletRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreWalletRequest;
use App\Models\Wallet;
use App\Notifications\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWalletRequest  $request
     */
    public function store(StoreWalletRequest $request)
    {
        // set user id
        $request['user_id'] = $request[0]->id;
        $request['currency'] = 'Nigerian Naira';
        $request['short'] = 'NGN';
        $request['symbol'] = '₦';

        return Wallet::create($request->only([
            'user_id',
            'currency',
            'short',
            'symbol',
        ]));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Wallet  $wallet
     * @param  \App\Http\Requests\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Wallet $wallet, $message = 'success', $code = 200)
    {
        return response()->json([
            'status' => true,
            'data' => $wallet,
            'message' => $message,
        ], $code);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Models\Wallet  $wallet
     * @param  \App\Http\Requests\StoreWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function withdraw(Wallet $wallet, StoreWalletRequest $request)
    {
        return $this->show($wallet);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Models\Wallet  $wallet
     * @param  \App\Http\Requests\FundWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function fund(Wallet $wallet, FundWalletRequest $request)
    {
        try {
            // generate payment link
            $data = [];
            $data['tx_ref'] = Str::uuid();
            $data['name'] = $request->user()->first_name . ' ' . $request->user()->last_name;
            $data['email'] = $request->user()->email_address;
            $data['phone_number'] = $request->user()->phone_number;
            $data['amount'] = $request->amount;
            $data['meta'] = [
                "consumer_id" => $request->user()->wallet->id,
                "consumer_mac" => TransactionChannel::CARD_TOP_UP(),
            ];
            $data['redirect_url'] = route('flw-webhook');

            $wallet->payment_link = (new FlutterwaveController())->generatePaymentLink($data);

            return $this->show($wallet);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    public function chargeCompleted($data)
    {
        // find virtual account
        if (!$wallet = Wallet::where('id', $data['meta']['consumer_id'])->first()) {
            return response()->json([], 422);
        }

        // credit user wallet
        $wallet->user->credit($data['amount']);

        // store transaction
        $transactionRequest = new StoreTransactionRequest();
        $transactionRequest['user_id'] = $wallet->user->id;
        $transactionRequest['identity'] = $data['id'];
        $transactionRequest['reference'] = $data['flw_ref'];
        $transactionRequest['type'] = TransactionType::CREDIT();
        $transactionRequest['channel'] = TransactionChannel::CARD_TOP_UP();
        $transactionRequest['amount'] = $data['amount'];
        $transactionRequest['narration'] = $data['narration'];
        $transactionRequest['status'] = TransactionStatus::SUCCESS();
        $transactionRequest['meta'] = json_encode($data);
        $transaction = (new TransactionController())->store($transactionRequest);

        // notify user of transaction
        $wallet->user->notify(new Transaction($transaction));

        return response()->json([]);
    }
}
