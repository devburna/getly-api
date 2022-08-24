<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\FundWalletRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\WithdrawWalletRequest;
use App\Models\Transaction as ModelsTransaction;
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
     * @param  \App\Http\Requests\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $message = 'success', $code = 200)
    {
        return response()->json([
            'status' => true,
            'data' => $request->user()->wallet,
            'message' => $message,
        ], $code);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\WithdrawWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function withdraw(WithdrawWalletRequest $request)
    {
        try {

            $transfer = match ($request->currency) {
                'usd' => (new FlutterwaveController())->bankTransfer($request->ngn),
                'ngn' => (new FlutterwaveController())->bankTransfer($request->ngn),
            };

            // debit user wallet
            $request->user()->debit($request->amount);

            // store transaction
            $transactionRequest = new StoreTransactionRequest();
            $transactionRequest['user_id'] = $request->user()->id;
            $transactionRequest['identity'] = $transfer['id'];
            $transactionRequest['reference'] = $transfer['reference'];
            $transactionRequest['type'] = TransactionType::DEBIT();
            $transactionRequest['channel'] = TransactionChannel::WALLET();
            $transactionRequest['amount'] = $transfer['amount'];
            $transactionRequest['narration'] = "Transfer to {$transfer['full_name']}";
            $transactionRequest['status'] = TransactionStatus::NEW();
            $transactionRequest['meta'] = json_encode($transfer);
            $transaction = (new TransactionController())->store($transactionRequest);

            // notify user of transaction
            $request->user()->notify(new Transaction($transaction));

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
     * @param  \App\Http\Requests\FundWalletRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function fund(FundWalletRequest $request)
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

            $request->user()->wallet->payment_link = (new FlutterwaveController())->generatePaymentLink($data)['data']['link'];

            return $this->show($request);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    public function chargeCompleted($data)
    {
        // find virtual account
        if (!$wallet = Wallet::where('id', $data['data']['meta']['consumer_id'])->first()) {
            return response()->json([], 422);
        }

        // credit user wallet
        $wallet->user->credit($data['data']['amount']);

        // store transaction
        $transactionRequest = new StoreTransactionRequest();
        $transactionRequest['user_id'] = $wallet->user->id;
        $transactionRequest['identity'] = $data['data']['id'];
        $transactionRequest['reference'] = $data['data']['flw_ref'];
        $transactionRequest['type'] = TransactionType::CREDIT();
        $transactionRequest['channel'] = TransactionChannel::CARD_TOP_UP();
        $transactionRequest['amount'] = $data['data']['amount'];
        $transactionRequest['narration'] = $data['data']['narration'];
        $transactionRequest['status'] = TransactionStatus::SUCCESS();
        $transactionRequest['meta'] = json_encode($data);
        $transaction = (new TransactionController())->store($transactionRequest);

        // notify user of transaction
        $wallet->user->notify(new Transaction($transaction));

        return response()->json([]);
    }

    public function transferCompleted($data)
    {
        // find transaction
        if (!$transaction = ModelsTransaction::where('reference', $data['data']['reference'])->first()) {
            return response()->json([], 422);
        }

        // verify transaction status
        if ($transaction->status->is(TransactionStatus::SUCCESS()) || $transaction->status->is(TransactionStatus::FAILED())) {
            return response()->json([], 422);
        }

        if (TransactionStatus::FAILED() === strtolower($data['data']['status'])) {

            // update transaction status to failed
            $transaction->update([
                'status' => strtolower($data['data']['status'])
            ]);

            // store transaction
            $transactionRequest = new StoreTransactionRequest();
            $transactionRequest['user_id'] = $transaction->user->id;
            $transactionRequest['identity'] = $data['data']['id'];
            $transactionRequest['reference'] = $data['data']['reference'];
            $transactionRequest['type'] = TransactionType::CREDIT();
            $transactionRequest['channel'] = TransactionChannel::WALLET();
            $transactionRequest['amount'] = $data['data']['amount'];
            $transactionRequest['narration'] = 'Transfer reversal';
            $transactionRequest['status'] = TransactionStatus::SUCCESS();
            $transactionRequest['meta'] = json_encode($data);
            $transaction = (new TransactionController())->store($transactionRequest);

            // credit user wallet
            $transaction->user->credit($data['data']['amount']);

            return response()->json([]);
        }

        // update transaction status
        $transaction->update([
            'status' => strtolower($data['data']['status'])
        ]);

        // notify user of transaction
        $transaction->user->notify(new Transaction($transaction));

        return response()->json([]);
    }
}
