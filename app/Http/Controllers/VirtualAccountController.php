<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreVirtualAccountRequest;
use App\Models\VirtualAccount;
use App\Notifications\Transaction;
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
            // checks duplicate wallet
            if ($request->user()->virtualAccount) {
                return $this->show($request);
            }

            // checks if bvn was approved
            if (!$request->approved) {
                throw ValidationException::withMessages([
                    'Please verify your BVN.'
                ]);
            }

            // get bvn info
            $bvn = (new IdentityPass())->verifyBvn((int)$request->bvn);

            // generate virtual card
            $bvn['id'] = $request->user()->id;
            $bvn['bvn'] = $request->bvn;
            $bvn['first_name'] = $bvn['bvn_data']['firstName'];
            $bvn['last_name'] = $bvn['bvn_data']['lastName'];
            $bvn['email_address'] = $request->user()->email_address;
            $bvn['phone_number'] = $bvn['bvn_data']['phoneNumber1'];

            $virtualAccount = (new FlutterwaveController())->createVirtualAccount($bvn);

            // store virtual account
            $virtualAccount['user_id'] = $request->user()->id;
            $virtualAccount['identity'] = $virtualAccount['data']['order_ref'];
            $virtualAccount['bank_name'] = $virtualAccount['data']['bank_name'];
            $virtualAccount['account_name'] = "{$request->user()->first_name} {$request->user()->last_name}";
            $virtualAccount['account_number'] = $virtualAccount['data']['account_number'];
            $virtualAccount['provider'] = $virtualAccount['data']['provider'];
            $virtualAccount['meta'] = json_encode($virtualAccount['data']);

            $storeVirtualAccountRequest = new StoreVirtualAccountRequest($virtualAccount);

            $request->user()->virtualAccount = $this->store($storeVirtualAccountRequest);

            return $this->show($request, 'success', 201);
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
            'provider',
            'meta'
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
        return $request->user()->virtualAccount;
        return response()->json([
            'status' => true,
            'data' => $request->user()->virtualAccount,
            'message' => $message,
        ], $code);
    }

    public function chargeCompleted($data)
    {
        // find virtual account
        if (!$virtualAccount = VirtualAccount::where('identity', $data['data']['tx_ref'])->first()) {
            return response()->json([], 422);
        }

        // set transaction data
        $transactionRequest = new StoreTransactionRequest();
        $transactionRequest['user_id'] = $virtualAccount->user->id;
        $transactionRequest['identity'] = $data['data']['id'];
        $transactionRequest['reference'] = $data['data']['flw_ref'];
        $transactionRequest['type'] = TransactionType::CREDIT();
        $transactionRequest['channel'] = TransactionChannel::VIRTUAL_ACCOUNT();
        $transactionRequest['amount'] = $data['data']['amount'];
        $transactionRequest['narration'] = $data['data']['narration'];
        $transactionRequest['meta'] = json_encode($data);

        // verify transaction status
        if (!$data['data']['status'] === 'successful') {
            // set status
            $transactionRequest['status'] = TransactionStatus::FAILED();
        } else {
            // set status
            $transactionRequest['status'] = TransactionStatus::SUCCESS();

            // credit user wallet
            $virtualAccount->user->credit($data['data']['amount']);
        }

        // store transaction
        $transaction = (new TransactionController())->store($transactionRequest);

        // notify user of transaction
        $virtualAccount->user->notify(new Transaction($transaction));

        return response()->json([]);
    }
}
