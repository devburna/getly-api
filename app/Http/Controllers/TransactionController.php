<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Http\Request;

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
                'data' => $transactions->paginate(50),
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
            'status' => $data['status'],
        ]);
    }

    // verify transactionÎ
    public function verify(Request $request)
    {
        $transaction = Transaction::where('reference', $request->tx_ref)->firstOrFail();

        if ($transaction->status = TransactionType::Success()) {
            return response()->json([
                'status' => true,
                'data' => $transaction,
                'message' => 'Payment verified',
            ]);
        }

        switch ($transaction->provider) {
            case 'glade':
                # code...
                break;

            default:

                $payment = (new FWController())->verifyPayment($request);
                break;
        }

        switch ($request->status) {
            case 'successful':
                if ($payment['data']['amount'] = $transaction->amount) {
                    $transaction->update([
                        'method' => $payment['data']['payment_type'],
                        'summary' => ucfirst(strtolower($payment['data']['narration'])),
                        'status' => TransactionType::Success(),
                    ]);

                    if ($transaction->channel === 'deposit') {
                        $transaction->user->wallet->update([
                            'balance' => $transaction->user->wallet->balance + $payment['data']['amount'],
                        ]);
                    }
                }

                return response()->json([
                    'status' => true,
                    'data' => $transaction,
                    'message' => $payment['data']['status'],
                ]);

                break;

            case 'cancelled':
                $transaction->update([
                    'summary' => 'Payment cancelled',
                    'status' => TransactionType::Cancelled(),
                ]);

                return response()->json([
                    'status' => false,
                    'data' => $transaction,
                    'message' => 'Payment was cancelled',
                ], 422);

                break;

            case 'failed':
                $transaction->update([
                    'summary' => 'Payment failed',
                    'status' => TransactionType::Failed(),
                ]);

                return response()->json([
                    'status' => false,
                    'data' => $transaction,
                    'message' => 'Payment failed',
                ], 422);

                break;
            default:

                return response()->json([
                    'status' => false,
                    'data' => $transaction,
                    'message' => 'Error verifying transaction. Please contact support',
                ], 422);
                break;
        }
    }
}
