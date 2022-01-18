<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\VirtualCardRequest;
use App\Models\VirtualCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VirtualCardController extends Controller
{
    public $reference;

    public function __construct()
    {
        $this->reference = str_shuffle(time() . mt_rand(1000, 9999));
    }

    public function create(VirtualCardRequest $request)
    {
        return DB::transaction(function () use ($request) {
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
                'provider' => 'glade',
            ]);

            (new TransactionController())->store([
                'user_id' => $request->user()->id,
                'reference' => $this->reference,
                'provider' => 'glade',
                'channel' => 'virtual_card',
                'amount' => $request->amount,
                'charges' => 2,
                'summary' => 'New virtual card',
                'spent' => true,
                'status' => TransactionType::Success(),
            ]);

            $request->user()->wallet->update([
                'balance' => $request->user()->wallet->balance - ($request->amount + env('GLADE_CARD_FEE')),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Card created.'
            ], 201);
        });
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

    public function cards(Request $request, VirtualCard $virtualCard)
    {
        $cards = $request->user()->virtualCards;

        if ($cards->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Not found'
            ], 404);
        }

        foreach ($cards as $virtualCard) {
            $virtualCard->details = (new GladeController())->virtualCardDetails($virtualCard->reference);;
        }

        return response()->json([
            'status' => true,
            'data' => $cards,
            'message' => 'Not found'
        ]);
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
            'balance' => $request->user()->wallet->balance - ($request->amount + env('GLADE_CARD_FEE')),
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

        (new TransactionController())->store([
            'user_id' => $request->user()->id,
            'reference' => $this->reference,
            'provider' => 'glade',
            'channel' => 'virtual_card',
            'amount' => $request->amount,
            'charges' => 0,
            'summary' => 'Virtual card withdrawal',
            'spent' => false,
            'status' => TransactionType::Success(),
        ]);

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
