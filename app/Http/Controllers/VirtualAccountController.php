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
            // checks if bvn was approved
            if (!$request->approved) {
                throw ValidationException::withMessages([
                    'Please verify your BVN.'
                ]);
            }

            // checks duplicate wallet
            if ($request->user()->virtualAccount) {
                return $this->show($request->user()->virtualAccount);
            }

            // get bvn info
            $bvn = (new FlutterwaveController())->verifyBvn($request->identity);

            // generate virtual card
            $bvn['id'] = $request->user()->id;
            $bvn['bvn'] = $bvn['bvn'];
            $bvn['first_name'] = $bvn['first_name'];
            $bvn['last_name'] = $bvn['last_name'];
            $bvn['email_address'] = $request->user()->email_address;
            $bvn['phone_number'] = $bvn['phone_number'];

            $virtualAccount = (new FlutterwaveController())->createVirtualAccount($bvn);

            // store virtual account
            $request['user_id'] = $request->user()->id;
            $request['identity'] = $virtualAccount['order_ref'];
            $request['account_name'] = "{$request->user()->first_name} {$request->user()->last_name}";
            $request['provider'] = 'flutterwave';
            $request->user()->virtualAccount = $this->store($request);

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
            'provider'
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
        return response()->json([
            'status' => true,
            'data' => $request->user()->virtualAccount,
            'message' => $message,
        ], $code);
    }

    public function chargeCompleted($data)
    {
        // find virtual account
        if (!$virtualAccount = VirtualAccount::where('identity', $data['tx_ref'])->first()) {
            return response()->json([], 422);
        }

        // credit user wallet
        $virtualAccount->user->credit($data['amount']);

        // store transaction
        $transactionRequest = new StoreTransactionRequest();
        $transactionRequest['user_id'] = $virtualAccount->user->id;
        $transactionRequest['identity'] = $data['id'];
        $transactionRequest['reference'] = $data['flw_ref'];
        $transactionRequest['type'] = TransactionType::CREDIT();
        $transactionRequest['channel'] = TransactionChannel::VIRTUAL_ACCOUNT();
        $transactionRequest['amount'] = $data['amount'];
        $transactionRequest['narration'] = $data['narration'];
        $transactionRequest['status'] = TransactionStatus::SUCCESS();
        $transactionRequest['meta'] = json_encode($data);
        $transaction = (new TransactionController())->store($transactionRequest);

        // notify user of transaction
        $virtualAccount->user->notify(new Transaction($transaction));

        return response()->json([]);
    }
}
