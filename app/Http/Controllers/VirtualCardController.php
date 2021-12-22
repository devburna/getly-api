<?php

namespace App\Http\Controllers;

use App\Http\Requests\VirtualCardRequest;
use App\Models\VirtualCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VirtualCardController extends Controller
{
    public function create(VirtualCardRequest $request)
    {
        return DB::transaction(function ($request) {
            $response =  (new GladeController())->createVirtualCard($request->user()->name, $request->amount);

            if (!$response) {
                return response()->json([
                    'status' => false,
                    'message' => 'Error creating card.'
                ], 422);
            }

            VirtualCard::create([
                'user_id' => $request->user()->id,
                'reference' => $response['card_data']['reference'],
                'provider' => $response['provider'],
            ]);

            $request->user()->wallet->update([
                'balance' => $request->user()->wallet->balance - ($request->amount + 2),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Card created.'
            ], 201);
        });
    }

    public function store($card)
    {
        VirtualCard::create([
            'user_id' => $card['user_id'],
            'reference' => $card['reference'],
            'provider' => $card['provider'],
        ]);
    }

    public function details(Request $request, VirtualCard $virtualCard)
    {
        if ($request->user()->cannot('view', $virtualCard)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }
        return (new GladeController())->virtualCardDetails($virtualCard->reference);
    }

    public function topup(VirtualCardRequest $request, VirtualCard $virtualCard)
    {
        if ($request->user()->cannot('view', $virtualCard)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }
        $response = (new GladeController())->fundVirtualCard($request->amount, $virtualCard->reference);

        if (!$response) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing topup.'
            ], 422);
        }

        $request->user()->wallet->update([
            'balance' => $request->user()->wallet->balance - $request->amount,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Topup was successful'
        ]);
    }

    public function withdraw(Request $request, VirtualCard $virtualCard)
    {
        if ($request->user()->cannot('view', $virtualCard)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }

        $response = (new GladeController())->withdrawVirtualCard($request->amount, $virtualCard->reference);

        if (!$response) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing withdrawal.'
            ], 422);
        }

        $request->user()->wallet->update([
            'balance' => $request->user()->wallet->balance + $request->amount,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Withdrawal was successful'
        ]);
    }

    public function transactions(Request $request, VirtualCard $virtualCard)
    {
        if ($request->user()->cannot('view', $virtualCard)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }

        return (new GladeController())->virtualCardTrx($virtualCard);
    }
}
