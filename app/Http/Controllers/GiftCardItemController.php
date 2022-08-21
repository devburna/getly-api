<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGiftCardItemRequest;
use App\Http\Requests\UpdateGiftCardItemRequest;
use App\Models\GiftCardItem;

class GiftCardItemController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreGiftCardItemRequest  $request
     */
    public function store(StoreGiftCardItemRequest $request)
    {
        return GiftCardItem::create($request->only([
            'gift_card_id',
            'link',
            'name',
            'price',
            'quantity',
            'image_url'
        ]));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\GiftCardItem  $giftCardItem
     * @return \Illuminate\Http\Response
     */
    public function show(GiftCardItem $giftCardItem)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateGiftCardItemRequest  $request
     * @param  \App\Models\GiftCardItem  $giftCardItem
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateGiftCardItemRequest $request, GiftCardItem $giftCardItem)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\GiftCardItem  $giftCardItem
     * @return \Illuminate\Http\Response
     */
    public function destroy(GiftCardItem $giftCardItem)
    {
        //
    }
}
