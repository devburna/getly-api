<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGiftCardItemRequest;
use App\Models\GiftCardItem;

class GiftCardItemController extends Controller
{
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
        return response()->json([
            'status' => true,
            'data' => $giftCardItem,
            'message' => 'success',
        ]);
    }
}
