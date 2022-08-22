<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreVirtualAccountRequest;
use App\Models\Transaction;
use App\Models\VirtualAccount;
use App\Notifications\Transaction as NotificationsTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            // checks if bvn was approved
            if (!$request->approved) {
                return response()->json([
                    'status' => false,
                    'data' => null,
                    'message' => 'Please verify your BVN.',
                ], 422);
            }

            // checks duplicate wallet
            if ($request->user()->virtualAccount) {
                return $this->show($request);
            }

            // get bvn info
            $bvn = (new FlutterwaveController())->verifyBvn($request->identity)->json()['data'];

            // generate virtual card
            $bvn['id'] = $request->user()->id;
            $bvn['bvn'] = $bvn['bvn'];
            $bvn['first_name'] = $bvn['first_name'];
            $bvn['last_name'] = $bvn['last_name'];
            $bvn['email_address'] = $request->user()->email_address;
            $bvn['phone_number'] = $bvn['phone_number'];

            $virtualAccount = (new FlutterwaveController())->createVirtualAccount($bvn)->json()['data'];

            // set user id
            $request['user_id'] = $request->user()->id;

            // set virtual account data
            $virtualAccount['user_id'] = $request->user()->id;
            $virtualAccount['identity'] = $virtualAccount['order_ref'];
            $virtualAccount['account_name'] = "{$request->user()->first_name} {$request->user()->last_name}";
            $virtualAccount['provider'] = 'flutterwave';

            // new request instance
            $storeVirtualAccountRequest = new StoreVirtualAccountRequest($virtualAccount);

            // store virtual account
            $request->user()->virtualAccount = $this->store($storeVirtualAccountRequest);

            return $this->show($request);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
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
            'bank_name',
            'account_number',
            'account_name',
            'provider'
        ]));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Http\Requests  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => $request->user()->virtualAccount,
            'message' => 'success',
        ]);
    }

    public function chargeCompleted(Request $request)
    {
        // verify hash
        if (!$request->header('verif-hash') === env('APP_KEY')) {
            return response()->json([], 401);
        }

        // find virtual account
        if (!$virtualAccount = VirtualAccount::where('identity', $request['data']['tx_ref'])->first()) {
            return response()->json([], 422);
        }

        // check for duplicate transaction
        if (Transaction::where('identity', $request['data']['id'])->first()) {
            return response()->json([], 422);
        }

        // verify status
        if (!$request['data']['status'] === 'successful') {
            return response()->json([], 422);
        }

        // credit user wallet
        $virtualAccount->user->credit($request['data']['amount']);

        // store transaction
        $transactionRequest = new StoreTransactionRequest();
        $transactionRequest['user_id'] = $virtualAccount->user->id;
        $transactionRequest['identity'] = $request['data']['id'];
        $transactionRequest['reference'] = $request['data']['flw_ref'];
        $transactionRequest['type'] = TransactionType::CREDIT();
        $transactionRequest['channel'] = TransactionChannel::VIRTUAL_ACCOUNT();
        $transactionRequest['amount'] = $request['data']['amount'];
        $transactionRequest['narration'] = $request['data']['narration'];
        $transactionRequest['status'] = TransactionStatus::SUCCESS();
        $transactionRequest['meta'] = json_encode($request->all());
        $transaction = (new TransactionController())->store($transactionRequest);

        // notify user
        $virtualAccount->user->notify(new NotificationsTransaction($transaction));

        return response()->json([]);
    }
}
