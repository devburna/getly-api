<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\VirtualCardRequest;
use App\Http\Requests\VirtualWithdrawRequest;
use App\Models\VirtualCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VirtualCardController extends Controller
{
    public function create(VirtualCardRequest $request)
    {
        return DB::transaction(function () use ($request) {

            if ($request->user()->virtualCard) {
                return response()->json([
                    'status' => false,
                    'message' => 'Card already exists!'
                ], 422);
            }

            $card =  (new FWController())->createVirtualCard($request->user()->name, $request->amount);

            if (!$card) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to create card at the moment!'
                ], 422);
            }

            VirtualCard::create([
                'user_id' => $request->user()->id,
                'reference' => $card['data']['id'],
                'provider' => 'flutterwave',
            ]);

            (new TransactionController())->store([
                'user_id' => $request->user()->id,
                'reference' => Str::uuid(),
                'provider' => 'flutterwave',
                'channel' => 'virtual_card',
                'amount' => $request->amount,
                'summary' => 'New virtual card purchase',
                'spent' => true,
                'status' => TransactionType::Success(),
            ]);

            $request->user()->wallet->update([
                'balance' => $request->user()->wallet->balance - $request->amount,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Card created successfully!'
            ], 201);
        });
    }

    public function details(Request $request)
    {
        if (!$request->user()->virtualCard) {
            return response()->json([
                'status' => false,
                'message' => 'No virtual card found!'
            ], 404);
        }

        $card = (new FWController())->getVirtualCard($request->user()->virtualCard->reference);

        if (!$card) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch card at the moment!'
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $card,
            'message' => 'Card fetched successfully!'
        ]);
    }

    public function topup(VirtualCardRequest $request)
    {
        if (!$request->user()->virtualCard) {
            return response()->json([
                'status' => false,
                'message' => 'No virtual card found!'
            ], 404);
        }

        $card = (new FWController())->fundVirtualCard($request->user()->virtualCard->reference, $request->amount);

        if (!$card) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fund card at the moment!'
            ], 422);
        }

        (new TransactionController())->store([
            'user_id' => $request->user()->id,
            'reference' => Str::uuid(),
            'provider' => 'flutterwave',
            'channel' => 'virtual_card',
            'amount' => $request->amount,
            'summary' => 'Virtual card topup',
            'spent' => true,
            'status' => TransactionType::Success(),
        ]);

        $request->user()->wallet->update([
            'balance' => $request->user()->wallet->balance - $request->amount,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Card funded successfully!'
        ]);
    }

    public function withdraw(VirtualWithdrawRequest $request)
    {
        if (!$request->user()->virtualCard) {
            return response()->json([
                'status' => false,
                'message' => 'No virtual card found!'
            ], 404);
        }

        $card = (new FWController())->withdrawVirtualCard($request->user()->virtualCard->reference, $request->amount);

        if (!$card) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to withdrawal from card at the moment!'
            ], 422);
        }

        (new TransactionController())->store([
            'user_id' => $request->user()->id,
            'reference' => Str::uuid(),
            'provider' => 'flutterwave',
            'channel' => 'virtual_card',
            'amount' => $request->amount,
            'summary' => 'Virtual card withdrawal',
            'spent' => false,
            'status' => TransactionType::Success(),
        ]);

        $request->user()->wallet->update([
            'balance' => $request->user()->wallet->balance + $request->amount,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Withdrawal successful!'
        ]);
    }

    public function transactions(Request $request)
    {
        if (!$request->user()->virtualCard) {
            return response()->json([
                'status' => false,
                'message' => 'No virtual card found!'
            ], 404);
        }

        $card = (new FWController())->virtualCardTransactions($request->user()->virtualCard->reference, $request->from, $request->to, $request->index, $request->size);

        if (!$card) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch card transactions at the moment!'
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $card['data'],
            'message' => $card['message']
        ]);
    }
}
