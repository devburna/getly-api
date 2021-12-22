<?php

namespace App\Http\Controllers;

use App\Http\Requests\VirtualCardRequest;
use App\Models\VirtualCard;
use Illuminate\Http\Request;

class VirtualCardController extends Controller
{
    public function create(VirtualCardRequest $request)
    {

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
    }

    public function store(VirtualCardRequest $request)
    {
        VirtualCard::create($request->only(['user_id', 'reference', 'provider']));
    }

    public function details(Request $request)
    {
        return (new GladeController())->virtualCardDetails($request);
    }

    public function topup(VirtualCardRequest $request)
    {
        return (new GladeController())->fundVirtualCard($request);
    }

    public function withdraw(Request $request)
    {
        return (new GladeController())->withdrawVirtualCard($request);
    }

    public function transactions(Request $request)
    {
        return (new GladeController())->virtualCardTrx($request);
    }
}
