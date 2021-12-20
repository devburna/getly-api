<?php

namespace App\Http\Controllers;

use App\Http\Requests\VirtualCardRequest;
use App\Models\VirtualCard;
use Illuminate\Http\Request;

class VirtualCardController extends Controller
{
    public function create(VirtualCardRequest $request)
    {
        return (new GladeController())->createVirtualCard($request);
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
