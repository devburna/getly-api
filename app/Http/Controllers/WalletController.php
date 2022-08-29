<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\FundVirtualCardRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\WithdrawWalletRequest;
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
            $request['name'] = $request->user()->full_name;
            $request['email'] = $request->user()->email_address;
            $request['phone'] = $request->user()->phone_number;
            $request['amount'] = $request->amount;
            $request['meta'] = [
                "consumer_id" => $request->user()->wallet->id,
                "consumer_mac" => TransactionChannel::CARD_TOP_UP(),
            ];
            $request['redirect_url'] = url('/dashboard');

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

    public function webHook($data)
    {
        try {
            // if user has virtual card
            if (!$wallet = Wallet::where('identity', $data['data']['meta']['consumer_id'])->first()) {
                throw ValidationException::withMessages(['Not allowed.']);
            }

            // get transaction status
            $status = match ($data['data']['transaction'] ? $data['data']['transaction']['status'] : $data['data']['status']) {
                'success' => TransactionStatus::SUCCESS(),
                'successful' => TransactionStatus::SUCCESS(),
                'new' => TransactionStatus::SUCCESS(),
                'pending' => TransactionStatus::SUCCESS(),
                default => TransactionStatus::FAILED()
            };

            // update transaction if exists
            if ($data['data']['transaction']) {
                $data['data']['transaction']->update([
                    'status' => $status
                ]);
            } else {
                $transaction = (new StoreTransactionRequest());
                $transaction['user_id'] = $wallet->user->id;
                $transaction['identity'] = $data['data']['tx_ref'];
                $transaction['reference'] = $data['data']['flw_ref'];
                $transaction['type'] = TransactionType::CREDIT();
                $transaction['channel'] = TransactionChannel::CARD_TOP_UP();
                $transaction['amount'] = $data['data']['amount'];
                $transaction['narration'] = $data['data']['narration'];
                $transaction['status'] = $status;
                $transaction['meta'] = json_encode($data);
            }

            // credit wallet if success
            if ($status === TransactionStatus::SUCCESS()) {
                $wallet->credit($data['data']['amount']);
            }

            // notify user of transaction
            $wallet->user->notify(new Transaction($data));
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
