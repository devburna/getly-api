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

            // checks if user can withdraw
            if (!$request->user()->hasFunds($request->amount)) {
                throw ValidationException::withMessages([
                    'Insufficient funds, please fund wallet and try again.'
                ]);
            }

            $transfer = match ($request->currency) {
                'usd' => (new FlutterwaveController())->bankTransfer($request->usd),
                'ngn' => (new FlutterwaveController())->bankTransfer($request->ngn),
            };

            // debit user wallet
            $request->user()->debit($request->amount);

            // store transaction
            $transactionRequest = new StoreTransactionRequest();
            $transactionRequest['user_id'] = $request->user()->id;
            $transactionRequest['identity'] = $transfer['data']['id'];
            $transactionRequest['reference'] = $transfer['data']['reference'];
            $transactionRequest['type'] = TransactionType::DEBIT();
            $transactionRequest['channel'] = TransactionChannel::WALLET();
            $transactionRequest['amount'] = $transfer['data']['amount'];
            $transactionRequest['narration'] = "Transfer to {$transfer['data']['full_name']}";
            $transactionRequest['status'] = TransactionStatus::NEW();
            $transactionRequest['meta'] = json_encode($transfer);
            $request->user()->transaction = (new TransactionController())->store($transactionRequest);

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
            $request['tx_ref'] = Str::uuid();
            $request['name'] = $request->user()->first_name . ' ' . $request->user()->last_name;
            $request['email'] = $request->user()->email_address;
            $request['phone_number'] = $request->user()->phone_number;
            $request['amount'] = $request->amount;
            $request['meta'] = [
                "consumer_id" => $request->user()->wallet->id,
                "consumer_mac" => TransactionChannel::CARD_TOP_UP(),
            ];
            $request['redirect_url'] = route('flw-webhook');

            $link = (new FlutterwaveController())->generatePaymentLink($request->all());

            $request->user()->wallet->payment_link = $link['data']['link'];

            return $this->show($request);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    public function chargeCompleted($data)
    {
        // find wallet
        if (!$wallet = Wallet::where('id', $data['data']['meta']['consumer_id'])->first()) {
            return response()->json([], 422);
        }

        // set transaction data
        $transactionRequest = new StoreTransactionRequest();
        $transactionRequest['user_id'] = $wallet->user->id;
        $transactionRequest['identity'] = $data['data']['id'];
        $transactionRequest['reference'] = $data['data']['tx_ref'];
        $transactionRequest['type'] = TransactionType::CREDIT();
        $transactionRequest['channel'] = TransactionChannel::CARD_TOP_UP();
        $transactionRequest['amount'] = $data['data']['amount'];
        $transactionRequest['narration'] = 'Wallet deposit';
        $transactionRequest['meta'] = json_encode($data);
        throw ValidationException::withMessages($data['data']);
        // verify transaction status
        if (!$data['data']['status'] === 'successful') {
            // set status
            $transactionRequest['status'] = TransactionStatus::FAILED();
        } else {
            // set status
            $transactionRequest['status'] = TransactionStatus::SUCCESS();

            // credit user wallet
            $wallet->user->credit($data['data']['amount']);
        }

        // store transaction
        $transaction = (new TransactionController())->store($transactionRequest);

        // notify user of transaction
        $wallet->user->notify(new Transaction($transaction));

        return response()->json([]);
    }

    public function transferCompleted($data)
    {
        if (!$data['data']['status'] === 'successful') {
            // update transaction status
            $data['transaction']->update([
                'status' => TransactionStatus::FAILED(),
            ]);

            // store transaction
            $transactionRequest = new StoreTransactionRequest();
            $transactionRequest['user_id'] = $data['transaction']->user->id;
            $transactionRequest['identity'] = $data['data']['id'];
            $transactionRequest['reference'] = $data['data']['reference'];
            $transactionRequest['type'] = TransactionType::CREDIT();
            $transactionRequest['channel'] = TransactionChannel::WALLET();
            $transactionRequest['amount'] = $data['data']['amount'];
            $transactionRequest['narration'] = 'Transfer reversal';
            $transactionRequest['status'] = TransactionStatus::SUCCESS();
            unset($data['transaction']);
            $transactionRequest['meta'] = json_encode($data);
            $transaction = (new TransactionController())->store($transactionRequest);

            // credit user wallet
            $transaction->user->credit($data['data']['amount']);

            // notify user of transaction
            $data['transaction']->user->notify(new Transaction($transaction));

            return response()->json([]);
        }

        // update transaction status
        $data['transaction']->update([
            'status' => TransactionStatus::SUCCESS(),
        ]);

        // notify user of transaction
        $data['transaction']->user->notify(new Transaction($data['transaction']));

        return response()->json([]);
    }
}
