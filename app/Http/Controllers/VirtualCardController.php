<?php

namespace App\Http\Controllers;

use App\Models\VirtualCard;
use Illuminate\Http\Request;

class VirtualCardController extends Controller
{
    public function create(Request $request)
    {
        return (new GladeController())->createVirtualCard($request);
    }

    public function store(Request $request)
    {
        VirtualCard::create($request->only(['user_id', 'reference', 'provider']));
    }

    public function details(Request $request)
    {
        return (new GladeController())->virtualCardDetails($request->user()->virtualCard->reference);
    }

    public function fund(Request $request)
    {
        return (new GladeController())->fundVirtualCard($request->user()->virtualCard->reference, $request->amount);
    }

    public function withdraw(Request $request)
    {
        return (new GladeController())->withdrawVirtualCard($request, $request->user()->virtualCard->reference, $request->amount);
    }

    public function transactions(Request $request)
    {
        return (new GladeController())->virtualCardTrx($request->user()->virtualCard->reference);
    }
}
