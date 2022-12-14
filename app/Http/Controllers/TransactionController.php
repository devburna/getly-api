<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Models\Webhook;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        try {
            // verify webhook hash
            if ($request->header('mono-webhook-secret') && ($request->header('mono-webhook-secret') !== env('MONO_WEBHOOK_SECRET'))) {
                return response()->json([], 401);
            }

            // Mono transfer received
            $virtual_account_events = ['issuing.transfer_received', 'issuing.transfer_failed', 'issuing.transfer_successful'];
            if (array_key_exists('event', $request->all()) && in_array($request['event'], $virtual_account_events)) {
                (new VirtualAccountController())->webHook($request->all());
            }

            // Mono card transaction received
            $virtual_card_events = ['issuing.card_transaction'];
            if (array_key_exists('event', $request->all()) && in_array($request['event'], $virtual_card_events)) {
                (new VirtualCardController())->webHook($request->all());
            }

            // store webhook info
            Webhook::create([
                'origin' => $request->server('SERVER_NAME'),
                'status' => true,
                'data' => json_encode($request->all()),
                'message' => 'success',
            ]);

            return response()->json([]);
        } catch (\Throwable $th) {

            // store webhook info
            Webhook::create([
                'origin' => $request->server('SERVER_NAME'),
                'status' => false,
                'data' => json_encode($request->all()),
                'message' => $th->getMessage(),
            ]);

            return response()->json([$th->getMessage()], 422);
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
