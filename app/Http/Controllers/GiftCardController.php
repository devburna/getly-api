<?php

namespace App\Http\Controllers;

use App\Enums\GiftCardStatus;
use App\Http\Requests\StoreGiftCardItemRequest;
use App\Http\Requests\StoreGiftCardRequest;
use App\Http\Requests\UpdateGiftCardRequest;
use App\Models\GiftCard;
use App\Models\User;
use App\Notifications\GiftCard as NotificationsGiftCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiftCardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->received) {
            // received gifts
            $giftcards = $request->user()->giftsReceived()->orderByDesc('created_at')->paginate(20);
        } else {
            // sent gifts
            $giftcards = $request->user()->giftsSent()->orderByDesc('created_at')->paginate(20);
        }

        foreach ($giftcards as $giftcard) {
            // add item count to data as wishes
            $giftcard->gift_links = $giftcard->items->count();

            // remove items from
            unset($giftcard->items);
        }

        return response()->json([
            'status' => true,
            'data' => $giftcards,
            'message' => 'success',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreGiftCardRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function create(StoreGiftCardRequest $request)
    {
        return DB::transaction(function () use ($request) {

            // set sender id
            $request['sender_id'] = $request->user()->id;

            // set user id if exists
            if ($user = User::where('email_address', $request->receiver_email_address)->orWhere('phone_number', $request->receiver_phone_number)->first()) {
                $request['user_id'] = $user->id;
            }

            // store gift card
            $giftCard = $this->store($request);

            // store gift card items
            foreach ($request->items as $item) {
                $giftCardItem = new StoreGiftCardItemRequest($item);

                // set gift card id
                $giftCardItem['gift_card_id'] = $giftCard->id;

                // store gift card item
                (new GiftCardItemController())->store($giftCardItem);
            }

            // notify receiver via email, whatsapp or sms
            $giftCard->notify(new NotificationsGiftCard($giftCard->createToken('redeem-gift-card', ['redeem-gift-card'])->plainTextToken));

            return $this->show($giftCard, 'success', 201);
        });
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreGiftCardRequest  $request
     */
    public function store(StoreGiftCardRequest $request)
    {
        // set status
        $request['status'] = GiftCardStatus::REDEEMABLE();

        return GiftCard::create($request->only([
            'user_id',
            'sender_id',
            'receiver_name',
            'receiver_email_address',
            'receiver_phone_number',
            'message',
            'status'
        ]));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\GiftCard  $giftCard
     * @return \Illuminate\Http\Response
     */
    public function show(GiftCard $giftCard, $message = 'success', $code = 200)
    {
        // add sender details data as wishes
        $giftCard->sender;

        // add gift card items to data
        $giftCard->items;

        return response()->json([
            'status' => true,
            'data' => $giftCard,
            'message' => $message,
        ], $code);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateGiftCardRequest  $request
     * @param  \App\Models\GiftCard  $giftCard
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateGiftCardRequest $request, GiftCard $giftCard)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\GiftCard  $giftCard
     * @return \Illuminate\Http\Response
     */
    public function destroy(GiftCard $giftCard)
    {
        // restore gift card if trashed or deleted it
        if ($giftCard->trashed()) {
            $giftCard->restore();
        } else {
            $giftCard->delete();
        }

        return $this->show($giftCard);
    }
}
