<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreVirtualAccountRequest;
use App\Models\VirtualAccount;
use App\Notifications\Transaction;
use App\Http\Requests\StoreMonoAccountHolderRequest;
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
                // get bvn info
                $bvn = (new MonoController())->verifyBvn($request->bvn);

                // verify bvn with user
                if (strtolower($request->user()->first_name) !== strtolower($bvn['data']['first_name']) || (strtolower($request->user()->last_name) !== strtolower($bvn['data']['last_name']))) {
                    throw ValidationException::withMessages(['The names provided on ' . config('app.name') . ' does not match with your BVN, please contact support at info@getly.app']);
                }

                $storeMonoAccountHolderRequest = (new StoreMonoAccountHolderRequest());
                $storeMonoAccountHolderRequest['user_id'] = $request->user()->id;
                $storeMonoAccountHolderRequest['first_name'] = $bvn['data']['first_name'];
                $storeMonoAccountHolderRequest['last_name'] = $bvn['data']['last_name'];
                $storeMonoAccountHolderRequest['bvn'] = $request->bvn;
                $storeMonoAccountHolderRequest['phone'] = $bvn['data']['phone'];
                $request->user()->monoAccountHolder = (new MonoAccountHolderController())->createAccountHolder($storeMonoAccountHolderRequest);
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
            unset($virtualAccount['data']['kyc_level']);

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

                switch ($data['data']['type']) {
                    case 'credit':
                        // set type to credit
                        $type = TransactionType::CREDIT();

                        // credit user
                        $virtualAccount->owner->credit($data['data']['amount'] / 100);

                        // set narration
                        $narration = $data['data']['narration'] ?? 'Wallet deposit';
                        break;

                    default:
                        // set type to debit
                        $type = TransactionType::DEBIT();

                        // set narration
                        $narration = "Transfer to {$data['data']['beneficiary']['account_name']}";
                        break;
                }

                // create transaction
                $storeTransactionRequest = (new StoreTransactionRequest());
                $storeTransactionRequest['user_id'] = $virtualAccount->owner->id;
                $storeTransactionRequest['identity'] = Str::uuid();
                $storeTransactionRequest['reference'] = Str::uuid();
                $storeTransactionRequest['type'] = $type;
                $storeTransactionRequest['channel'] = TransactionChannel::VIRTUAL_ACCOUNT();
                $storeTransactionRequest['amount'] = $data['data']['amount'] / 100;
                $storeTransactionRequest['narration'] = $narration;
                $storeTransactionRequest['status'] = $status;
                $storeTransactionRequest['meta'] = json_encode($data);
                $transaction = (new TransactionController())->store($storeTransactionRequest);

                // notify user of transaction
                // $virtualAccount->owner->notify(new Transaction($transaction));

                // reverse if failed
                if ($data['event'] === 'issuing.transfer_failed') {

                    // refund user
                    $virtualAccount->owner->credit($data['data']['amount'] / 100);

                    // create transaction
                    $storeTransactionRequest = (new StoreTransactionRequest());
                    $storeTransactionRequest['user_id'] = $virtualAccount->owner->id;
                    $storeTransactionRequest['identity'] = Str::uuid();
                    $storeTransactionRequest['reference'] = Str::uuid();
                    $storeTransactionRequest['type'] = TransactionType::CREDIT();
                    $storeTransactionRequest['channel'] = TransactionChannel::VIRTUAL_ACCOUNT();
                    $storeTransactionRequest['amount'] = $data['data']['amount'] / 100;
                    $storeTransactionRequest['narration'] = 'Reversal';
                    $storeTransactionRequest['status'] = TransactionStatus::SUCCESS();
                    $storeTransactionRequest['meta'] = json_encode($data);
                    $transaction = (new TransactionController())->store($storeTransactionRequest);

                    // notify user of transaction
                    // $virtualAccount->owner->notify(new Transaction($transaction));
                }
            });
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
