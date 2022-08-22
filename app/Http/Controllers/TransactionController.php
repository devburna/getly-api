<?php

namespace App\Http\Controllers;

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
            // verify hash
            if (!$request->header('verif-hash') || !$request->header('verif-hash') === env('APP_KEY')) {
                return response()->json(['data' => $request->header('verif-hash')], 401);
            }

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

            if ($response['event'] && $response['event'] === 'charge.completed') {
                return (new VirtualAccount())->chargeCompleted($response['data']);
            }

            if ($response['data']['meta'] && $response['data']['meta']['consumer_mac'] === 'fund-wallet') {
                return (new WalletController())->chargeCompleted($response['data']);
            }

            return response()->json([], 422);
        } catch (\Throwable $th) {
            return response()->json([], 401);
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
