<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $transactions = $request->user()->transactions();

        if ($transactions->get()->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Not found'
            ], 404);
        } else {
            return response()->json([
                'status' => true,
                'data' => $transactions->orderByDesc('created_at')->paginate(50),
                'message' => 'Found'
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store($data)
    {
        Transaction::create([
            'user_id' => $data['user_id'],
            'reference' => $data['reference'],
            'provider' => $data['provider'],
            'channel' => $data['channel'],
            'amount' => $data['amount'],
            'summary' => $data['summary'],
            'spent' => $data['spent'],
            'status' => $data['status'],
        ]);
    }

    // verify transaction
    public function verify(Request $request)
    {
        return DB::transaction(function () use ($request) {
            switch ($request->status) {
                case 'successful':
                    $transaction = Transaction::where('reference', $request->tx_ref)->first();
                    $payment = (new FWController())->verifyPayment($request->transaction_id);

                    if (!$transaction || !$payment) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Error verifying transaction'
                        ], 422);
                    }

                    if ($transaction->status = TransactionType::Success()) {
                        return response()->json([
                            'status' => true,
                            'message' => 'Payment successful'
                        ]);
                    }

                    if ($payment['data']['amount'] != $transaction->amount) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Error verifying transaction'
                        ], 422);
                    }

                    $transaction->update([
                        'method' => $payment['data']['payment_type'],
                        'status' => TransactionType::Success(),
                    ]);

                    switch ($transaction->channel) {
                        case 'deposit':
                            $transaction->user->wallet->update([
                                'balance' => $transaction->user->wallet->balance + $payment['data']['amount'],
                            ]);
                            break;

                        case 'transfer':
                            $transaction->user->wallet->update([
                                'balance' => $transaction->user->wallet->balance - $payment['data']['amount'],
                            ]);
                            break;

                        default:
                            # code...
                            break;
                    }

                    return response()->json([
                        'status' => true,
                        'message' => 'Payment successful'
                    ]);

                    break;
                case 'cancelled':
                    return response()->json([
                        'status' => false,
                        'message' => 'Payment cancelled'
                    ], 422);
                    break;

                default:
                    return response()->json([
                        'status' => false,
                        'message' => 'Error verifying payment. Please contact support'
                    ], 422);
                    break;
            }
        });
    }
}
