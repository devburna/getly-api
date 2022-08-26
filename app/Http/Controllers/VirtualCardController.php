<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\FundVirtualCardRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\StoreVirtualCardRequest;
use App\Http\Requests\ToggleVirtualCardRequest;
use App\Models\VirtualCard;
use App\Notifications\VirtualCardTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class VirtualCardController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function create(StoreVirtualCardRequest $request)
    {
        try {

            // checks if user has a virtual card
            if ($request->user()->virtualCard) {
                return $this->show($request);
            }

            // if user has funds
            if (!$request->user()->hasFunds(env('MONO_VIRTUAL_CARD_FEE'))) {
                throw ValidationException::withMessages([
                    'Insufficient funds, please fund wallet and try again.'
                ]);
            }

            // create a mono account if not found
            if (!$request->user()->monoAccountHolder) {
                throw ValidationException::withMessages([
                    'Please verify your kyc.'
                ]);
            }

            // generate virtual card
            $virtualCard = (new MonoController())->createVirtualCard($request->user()->monoAccountHolder->identity);
            $request['user_id'] = $request->user()->id;
            $request['identity'] = $virtualCard['data']['id'];
            $request['provider'] = $virtualCard['data']['provider'];
            $request['meta'] = json_encode($virtualCard);

            // store virtual card
            $request->user()->virtualCard = $this->store($request);

            // debit user wallet
            $request->user()->debit(env('MONO_VIRTUAL_CARD_FEE'));

            // create transaction
            $storeTransactionRequest = (new StoreTransactionRequest());
            $storeTransactionRequest['user_id'] = $request->user()->id;
            $storeTransactionRequest['identity'] = Str::uuid();
            $storeTransactionRequest['reference'] = Str::uuid();
            $storeTransactionRequest['type'] = TransactionType::DEBIT();
            $storeTransactionRequest['channel'] = TransactionChannel::WALLET();
            $storeTransactionRequest['amount'] = env('MONO_VIRTUAL_CARD_FEE');
            $storeTransactionRequest['narration'] = 'New virtual card';
            $storeTransactionRequest['status'] = TransactionStatus::SUCCESS();
            $storeTransactionRequest['meta'] = json_encode($virtualCard);
            $transaction = (new TransactionController())->store($storeTransactionRequest);

            // notify user of transaction
            $request->user()->notify(new VirtualCardTransaction($transaction));

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
     * @param  \App\Http\Requests\StoreVirtualCardRequest  $request
     */
    public function store(StoreVirtualCardRequest $request)
    {
        return VirtualCard::create($request->only([
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
            // if user has virtual card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card created for this account.']);
            }

            // get virtual card details
            $virtualCard = (new MonoController())->virtualCardDetails($request->user()->virtualCard->identity);

            // clean response data
            unset($virtualCard['data']['id']);
            unset($virtualCard['data']['disposable']);
            unset($virtualCard['data']['created_at']);
            unset($virtualCard['data']['account_holder']);
            unset($virtualCard['data']['meta']);

            // convert balance to kobo
            $virtualCard['data']['balance'] = $virtualCard['data']['balance'] / 100;

            // decrypt data
            if ($request->reveal) {
                // validate request
                $request->validate([
                    'reveal' => 'boolean'
                ]);
                $virtualCard['data']['card_number'] = $this->decryptString($virtualCard['data']['card_number']);
                $virtualCard['data']['cvv'] = $this->decryptString($virtualCard['data']['cvv']);
                $virtualCard['data']['expiry_month'] = $this->decryptString($virtualCard['data']['expiry_month']);
                $virtualCard['data']['expiry_year'] = $this->decryptString($virtualCard['data']['expiry_year']);
                $virtualCard['data']['last_four'] = $this->decryptString($virtualCard['data']['last_four']);
                $virtualCard['data']['pin'] = $this->decryptString($virtualCard['data']['pin']);
            }

            return response()->json([
                'status' => true,
                'data' => $virtualCard['data'],
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

    public function fund(FundVirtualCardRequest $request)
    {
        try {
            // if user has virtual card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card created for this account.']);
            }

            // if user has funds
            if (!$request->user()->hasFunds($request->amount)) {
                throw ValidationException::withMessages([
                    'Insufficient funds, please fund wallet and try again.'
                ]);
            }

            // fund virtual card
            $request['card'] = $request->user()->virtualCard->identity;
            $virtualCard = (new MonoController())->fundVirtualCard($request->all());

            // debit user wallet
            $request->user()->debit($request->amount);

            // create transaction
            $storeTransactionRequest = (new StoreTransactionRequest());
            $storeTransactionRequest['user_id'] = $request->user()->id;
            $storeTransactionRequest['identity'] = Str::uuid();
            $storeTransactionRequest['reference'] = Str::uuid();
            $storeTransactionRequest['type'] = TransactionType::DEBIT();
            $storeTransactionRequest['channel'] = TransactionChannel::WALLET();
            $storeTransactionRequest['amount'] = $request->amount;
            $storeTransactionRequest['narration'] = 'Virtual card funding';
            $storeTransactionRequest['status'] = TransactionStatus::SUCCESS();
            $storeTransactionRequest['meta'] = json_encode($virtualCard);
            $transaction = (new TransactionController())->store($storeTransactionRequest);

            // notify user of transaction
            $request->user()->notify(new VirtualCardTransaction($transaction));

            return response()->json([
                'status' => true,
                'data' => [
                    'amount' => $request->amount,
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

    public function transactions(Request $request)
    {
        try {
            // if user has virtual card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card created for this account.']);
            }

            // get virtual card transactions
            $request['card'] = $request->user()->virtualCard->identity;
            $request['page'] = $request->page;
            $request['from'] = $request->from;
            $request['to'] = $request->to;
            $virtualCard = (new MonoController())->virtualCardTransactions($request->all());

            return response()->json([
                'status' => true,
                'data' => $virtualCard['data'],
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

    public function toggle(ToggleVirtualCardRequest $request)
    {
        try {
            // if user has virtual card
            if (!$request->user()->virtualCard) {
                throw ValidationException::withMessages(['No virtual card created for this account.']);
            }

            // get virtual card transactions
            $request['card'] = $request->user()->virtualCard->identity;
            $request['action'] = $request->action;
            (new MonoController())->virtualCardTransactions($request->all());

            return response()->json([
                'status' => true,
                'data' => [
                    'action' =>  $request->action
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

    public function webHook($data)
    {
        try {
            // if user has virtual card
            if (!$virtualCard = VirtualCard::where('identity', $data['data']['card'])->first()) {
                throw ValidationException::withMessages(['Not allowed.']);
            }

            // notify user of transaction
            // $virtualCard->owner->notify(new VirtualCardTransaction($data['data']));

            return response()->json([]);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }

    function decryptString($encryptedData)
    {

        $encryptedBin = hex2bin($encryptedData);

        $iv = substr($encryptedBin, 0, 16);

        $encryptedText = substr($encryptedBin, 16);

        $key = substr(base64_encode(hash('sha256', env('MONO_SEC_KEY'), true)), 0, 32);

        $algorithm = "aes-256-cbc";

        $decryptedData = openssl_decrypt($encryptedText, $algorithm, $key, OPENSSL_RAW_DATA, $iv);


        return $decryptedData;
    }
}
