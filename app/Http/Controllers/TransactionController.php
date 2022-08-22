<?php

namespace App\Http\Controllers;

use App\Enums\TransactionChannel;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Models\VirtualAccount;
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

            // verify transaction
            $response = (new FlutterwaveController())->verifyTransaction($request->transaction_id)->json();

            // check for duplicate transaction
            if (Transaction::where('identity', $response['data']['id'])->first()) {
                return response()->json([], 422);
            }

            // verify status
            if (!$response['data']['status'] === 'successful') {
                return response()->json([], 422);
            }

            // check for virtual account transaction
            if (array_key_exists('event', $response) && $response['event'] === 'charge.completed') {
                return (new VirtualAccount())->chargeCompleted($response['data']);
            }

            // check for card-top-up  transaction
            if (array_key_exists('meta', $response['data']) && $response['data']['meta']['consumer_mac'] === TransactionChannel::CARD_TOP_UP()) {
                return (new WalletController())->chargeCompleted($response['data']);
            }

            return response()->json([], 422);
        } catch (\Throwable $th) {
            return response()->json([], 422);
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
