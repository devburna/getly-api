<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\GetlistItem;
use App\Models\Transaction;
use App\Models\VirtualAccount;
use App\Models\Webhook;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \App\Http\Requests  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $transactions = $request->user()->transactions()->orderByDesc('created_at')->paginate();

        return response()->json([
            'status' => true,
            'data' => $transactions,
            'message' => 'success',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  \App\Http\Requests  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        try {

            // verify request is from flutterwave
            if ($request->header('verify-hash') && !$request->header('verify-hash') === env('FLUTTERWAVE_SECRET_HASH')) {
                return response()->json([], 401);
            }

            // verify transaction
            $response = (new FlutterwaveController())->verifyTransaction($request->transaction_id);

            // store webhook info
            Webhook::create([
                'origin' => $request->server('SERVER_NAME'),
                'status' => true,
                'data' => json_encode($response['data']),
                'message' => $response['message'],
            ]);

            // find transaction
            $transaction = Transaction::where('identity', $response['data']['id'])->first();

            // check for duplicate transaction
            if ($transaction && $transaction->status->is(TransactionStatus::SUCCESS()) || $transaction->status->is(TransactionStatus::FAILED())) {
                return response()->json([], 422);
            }

            // check for card-top-up  transaction
            if (array_key_exists('meta', $response['data']) && $response['data']['meta']['consumer_mac'] === 'card-top-up') {
                return (new WalletController())->chargeCompleted($response);
            }

            // check for contribution or buy  transaction
            if (array_key_exists('meta', $response['data']) && $response['data']['meta']['consumer_mac'] === 'contribute' || $response['data']['meta']['consumer_mac'] === 'buy') {
                return (new GetlistItem())->chargeCompleted($response);
            }

            // check for transfer transaction
            if (array_key_exists('event', $response) && $response['event'] === 'transfer.completed' && array_key_exists('event.type', $response) && $response['event.type'] === 'Transfer' || $response['event.type'] === 'transfer') {
                // add transaction to response
                $response['transaction'] = $transaction;

                return (new WalletController())->transferCompleted($response);
            }

            // check for virtual account transaction
            if (array_key_exists('event', $response) && $response['event'] === 'charge.completed') {
                return (new VirtualAccount())->chargeCompleted($response);
            }

            return response()->json([], 422);
        } catch (\Throwable $th) {
            // store webhook info
            Webhook::create([
                'origin' => $request->server('SERVER_NAME'),
                'status' => false,
                'message' => $th->getMessage(),
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreTransactionRequest  $request
     */
    public function store(StoreTransactionRequest $request)
    {
        return Transaction::create($request->only([
            'user_id',
            'identity',
            'reference',
            'type',
            'channel',
            'amount',
            'narration',
            'status',
            'meta'
        ]));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Transaction  $transaction
     * @return \Illuminate\Http\Response
     */
    public function show(Transaction $transaction)
    {
        return response()->json([
            'status' => true,
            'data' => $transaction,
            'message' => 'success',
        ]);
    }
}
