<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\FundWalletRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreWalletRequest;
use App\Models\Wallet;
use App\Notifications\Transaction as NotificationsTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * @param  \App\Http\Requests\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => $request->user()->wallet,
            'message' => 'success',
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\StoreWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function withdraw(StoreWalletRequest $request)
    {
        return response()->json([
            'status' => true,
            'data' => $request->all(),
            'message' => 'success',
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\FundWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function fund(FundWalletRequest $request)
    {
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

        $link = (new FlutterwaveController())->generatePaymentLink($data)->json()['data'];

        return response()->json([
            'status' => true,
            'data' => [
                'payment_link' => $link['link'],
            ],
            'message' => 'success',
        ]);
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
        $wallet->user->notify(new NotificationsTransaction($transaction));

        return response()->json([
            'status' => true,
            'data' => $transaction,
            'message' => 'success',
        ], 200);
    }
}
