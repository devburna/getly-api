<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\FundVirtualCardRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\VerifyFlutterwaveTransactionRequest;
use App\Http\Requests\WithdrawWalletRequest;
use App\Models\Transaction as ModelsTransaction;
use App\Models\Wallet;
use App\Notifications\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        return Wallet::create($request->only([
            'user_id',
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

    public function fund(FundVirtualCardRequest $request)
    {
        try {

            // generate payment link
            $request['tx_ref'] = Str::uuid();
            $request['name'] = "{$request->user()->first_name} {$request->user()->last_name}";
            $request['email'] = $request->user()->email_address;
            $request['phone'] = $request->user()->phone_number;
            $request['amount'] = $request->amount;
            $request['meta'] = [
                "consumer_id" => $request->user()->wallet->id,
                "consumer_mac" => TransactionChannel::CARD_TOP_UP(),
            ];
            $request['redirect_url'] = config('app.url') . '/dashboard/wallet';

            $link = (new FlutterwaveController())->generatePaymentLink($request->all());

            // set payment link
            $request->user()->wallet->payment_link = $link['data']['link'];

            return response()->json([
                'status' => true,
                'data' => [
                    'wallet' => $request->user()->wallet,
                    'amount' => $request->amount
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

    public function transfer(WithdrawWalletRequest $request)
    {
        try {

            return DB::transaction(function () use ($request) {
                if (!$request->user()->virtualAccount) {
                    throw ValidationException::withMessages(['Transfer not available at the moment.']);
                }

                // if user has funds
                if (!$request->user()->hasFunds($request->amount)) {
                    throw ValidationException::withMessages([
                        'Insufficient funds, please fund wallet and try again.'
                    ]);
                }

                // transfer with mono
                $request['cust'] = $request->user()->virtualAccount->identity;
                (new MonoController())->virtualAccountTransfer($request->all());

                // debit user wallet
                $request->user()->debit($request->amount);

                return response()->json([
                    'status' => true,
                    'data' => [
                        'amount' => $request->amount
                    ],
                    'message' => 'success',
                ]);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage()
            ], 422);
        }
    }

    public function webHook(VerifyFlutterwaveTransactionRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                // verify transaction
                $transaction = (new FlutterwaveController())->verifyTransaction($request->transaction_id);

                // checks duplicate entry
                if (ModelsTransaction::where('identity', $transaction['data']['tx_ref'])->first()) {
                    throw ValidationException::withMessages(['Duplicate transaction found.']);
                }

                // get transaction status
                $status = match ($transaction['data']['status']) {
                    'success' => TransactionStatus::SUCCESS(),
                    'successful' => TransactionStatus::SUCCESS(),
                    'new' => TransactionStatus::SUCCESS(),
                    'pending' => TransactionStatus::SUCCESS(),
                    default => TransactionStatus::FAILED()
                };

                // set channel
                $channel = match ($transaction['data']['meta']['consumer_mac']) {
                    'card-top-up' => TransactionChannel::CARD_TOP_UP(),
                    default => throw ValidationException::withMessages(['Error occured, kindly reach out to support ASAP!'])
                };

                // store transaction
                $storeTransactionRequest = (new StoreTransactionRequest($transaction));
                $storeTransactionRequest['user_id'] = $request->user()->id;
                $storeTransactionRequest['identity'] = $transaction['data']['tx_ref'];
                $storeTransactionRequest['reference'] = $transaction['data']['flw_ref'];
                $storeTransactionRequest['type'] = TransactionType::CREDIT();
                $storeTransactionRequest['channel'] = $channel;
                $storeTransactionRequest['amount'] = $transaction['data']['amount'];
                $storeTransactionRequest['narration'] = $transaction['data']['narration'];
                $storeTransactionRequest['status'] = $status;
                $storeTransactionRequest['meta'] = json_encode($transaction);
                $storedTransaction = (new TransactionController())->store($storeTransactionRequest);

                // credit wallet if success
                if ($storedTransaction->status->is(TransactionStatus::SUCCESS())) {
                    $request->user()->credit($storedTransaction->amount);
                }

                // notify gift owner
                // $request->user()->notify(new Transaction($storedTransaction));

                return $this->show($request);
            });
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'data' => null,
                'message' => $th->getMessage(),
            ], 422);
        }
    }
}
