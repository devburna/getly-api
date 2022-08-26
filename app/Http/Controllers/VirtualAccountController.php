<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\StoreMonoAccountHolderRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreVirtualAccountRequest;
use App\Models\VirtualAccount;
use App\Models\Webhook;
use App\Notifications\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class VirtualAccountController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @param  \App\Http\Requests\StoreVirtualAccountRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function create(StoreVirtualAccountRequest $request)
    {
        try {
            // checks if user has a virtual account
            if ($request->user()->virtualAccount) {
                return $this->show($request);
            }

            // create a mono account if not found
            if (!$request->user()->monoAccountHolder) {
                throw ValidationException::withMessages([
                    'Please verify your kyc.'
                ]);
            }

            // generate virtual account
            $virtualAccount = (new MonoController())->createVirtualAccount($request->user()->monoAccountHolder->identity);
            $request['user_id'] = $request->user()->id;
            $request['identity'] = $virtualAccount['data']['id'];
            $request['provider'] = $virtualAccount['data']['provider'];
            $request['meta'] = json_encode($virtualAccount);

            // store virtual account
            $request->user()->virtualAccount = $this->store($request);

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
     * @param  \App\Http\Requests\StoreVirtualAccountRequest  $request
     */
    public function store(StoreVirtualAccountRequest $request)
    {
        return VirtualAccount::create($request->only([
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
            // checks if user has a virtual account
            if (!$request->user()->virtualAccount) {
                throw ValidationException::withMessages(['No virtual account created for this account.']);
            }

            // get virtual account details
            $virtualAccount = (new MonoController())->virtualAccountDetails($request->user()->virtualAccount->identity);

            // clean response data
            unset($virtualAccount['data']['id']);
            unset($virtualAccount['data']['budget']);
            unset($virtualAccount['data']['type']);
            unset($virtualAccount['data']['bank_code']);
            unset($virtualAccount['data']['currency']);
            unset($virtualAccount['data']['balance']);
            unset($virtualAccount['data']['created_at']);
            unset($virtualAccount['data']['updated_at']);
            unset($virtualAccount['data']['account_holder']);

            return response()->json([
                'status' => true,
                'data' => $virtualAccount['data'],
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

    public function webHook($data)
    {
        try {
            return DB::transaction(function () use ($data) {
                // checks if user has a virtual account
                if (!$virtualAccount = VirtualAccount::where('identity', $data['data']['account'])->first()) {
                    throw ValidationException::withMessages(['Not allowed.']);
                }

                // set transaction status
                $status = match ($data['event']) {
                    'issuing.transfer_successful' => TransactionStatus::SUCCESS(),
                    'issuing.transfer_failed' => TransactionStatus::FAILED(),
                    'issuing.transfer_received' => TransactionStatus::SUCCESS()
                };

                // set transaction type
                $type = match ($data['data']['type']) {
                    'debit' => TransactionType::DEBIT(),
                    'credit' => TransactionType::CREDIT()
                };

                // debit or credit
                match ($type) {
                    'debit' => $virtualAccount->owner->debit($data['data']['amount'] / 100),
                    'credit' => $virtualAccount->owner->credit($data['data']['amount'] / 100)
                };

                // create transaction
                $storeTransactionRequest = (new StoreTransactionRequest());
                $storeTransactionRequest['user_id'] = $virtualAccount->owner->id;
                $storeTransactionRequest['identity'] = Str::uuid();
                $storeTransactionRequest['reference'] = Str::uuid();
                $storeTransactionRequest['type'] = $type;
                $storeTransactionRequest['channel'] = TransactionChannel::VIRTUAL_ACCOUNT();
                $storeTransactionRequest['amount'] = $data['data']['amount'] / 100;
                $storeTransactionRequest['narration'] = $data['data']['narration'];
                $storeTransactionRequest['status'] = $status;
                $storeTransactionRequest['meta'] = json_encode($data);
                $transaction = (new TransactionController())->store($storeTransactionRequest);

                // notify user of transaction
                // $transaction->owner->notify(new Transaction($transaction));

                // store webhook info
                Webhook::create([
                    'origin' => 'mono',
                    'status' => true,
                    'data' => json_encode($data),
                    'message' => 'success',
                ]);

                return response()->json([]);
            });
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
