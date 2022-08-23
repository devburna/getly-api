<?php

namespace App\Http\Controllers;

use App\Enums\GiftCardStatus;
use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\StoreGiftCardItemRequest;
use App\Http\Requests\StoreGiftCardRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\GiftCard;
use App\Models\User;
use App\Notifications\GiftCard as NotificationsGiftCard;
use App\Notifications\Redeemed;
use App\Notifications\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Str;

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

            // checks if sender can fund gift
            if (!$request->user()->hasFunds(array_sum(array_column($request->items, 'price')))) {
                throw ValidationException::withMessages([
                    'Insufficient funds, please fund wallet and try again.'
                ]);
            }

            // set sender id
            $request['sender_id'] = $request->user()->id;

            // set user id if exists
            if ($user = User::where('email_address', $request->receiver_email_address)->orWhere('phone_number', $request->receiver_phone_number)->first()) {
                $request['user_id'] = $user->id;
            }

            // store gift card
            $giftCard = $this->store($request);

            // store gift card items
            foreach ($request->items as $key => $item) {

                $giftCardItem = new StoreGiftCardItemRequest($item);

                // set gift card id
                $giftCardItem['gift_card_id'] = $giftCard->id;

                // upload image
                // $request['image_url'] = (new UploadApi())->upload($request->image->path(), [
                //     'folder' => config('app.name') . '/gifts-cards/',
                //     'public_id' => $giftCard->id . $key,
                //     'overwrite' => true,
                //     // 'notification_url' => '',
                //     'resource_type' => 'image'
                // ])['secure_url'];

                $giftCardItem['image_url'] = $item['image'];

                // store gift card item
                (new GiftCardItemController())->store($giftCardItem);
            }

            // debit sender wallet
            $request->user()->debit(array_sum(array_column($request->items, 'price')));

            // store transaction
            $transactionRequest = new StoreTransactionRequest();
            $transactionRequest['user_id'] = $request->user()->id;
            $transactionRequest['identity'] = Str::uuid();
            $transactionRequest['reference'] = Str::uuid();
            $transactionRequest['type'] = TransactionType::DEBIT();
            $transactionRequest['channel'] = TransactionChannel::WALLET();
            $transactionRequest['amount'] = array_sum(array_column($request->items, 'price'));
            $transactionRequest['narration'] = 'Sent gift card';
            $transactionRequest['status'] = TransactionStatus::SUCCESS();
            $transactionRequest['meta'] = json_encode($request->all());
            $transaction = (new TransactionController())->store($transactionRequest);

            // notify user of transaction
            $request->user()->notify(new Transaction($transaction));

            // notify receiver via email, whatsapp or sms
            $giftCard['id'] = $giftCard->id;
            $giftCard->notify(new NotificationsGiftCard());

            return $this->show($giftCard, "You’ve just sent your gift card to {$request->receiver_name}", 201);
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

    /**
     * Display the specified resource.
     *
     *
     * @param  \App\Models\GiftCard  $giftCard
     * @return \Illuminate\Http\Response
     */
    public function preview(GiftCard $giftCard)
    {
        return $this->show($giftCard);
    }

    /**
     * Display the specified resource.
     *
     *
     * @param  \App\Models\GiftCard  $giftCard
     * @param  \App\Http\Requests\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function redeem(Request $request, GiftCard $giftCard)
    {
        try {
            return DB::transaction(function () use ($request, $giftCard) {

                // checks if receiver owns gift
                if ($giftCard->user_id && !$giftCard->user_id === $request->user()->id) {
                    throw ValidationException::withMessages(["This gift does'nt belong to you."]);
                }

                // checks if gift card has been redeemed
                if (!$giftCard->status->is(GiftCardStatus::REDEEMABLE())) {
                    throw ValidationException::withMessages(['This gift card has already been ' . GiftCardStatus::CLAIMED() . '.']);
                }

                // update gift card status
                $giftCard->update([
                    'user_id' => $request->user()->id,
                    'status' => GiftCardStatus::CLAIMED()
                ]);

                // credit receiver wallet
                $request->user()->credit($giftCard->items->sum('price'));

                // store transaction
                $transactionRequest = new StoreTransactionRequest();
                $transactionRequest['user_id'] = $request->user()->id;
                $transactionRequest['identity'] = Str::uuid();
                $transactionRequest['reference'] = Str::uuid();
                $transactionRequest['type'] = TransactionType::CREDIT();
                $transactionRequest['channel'] = TransactionChannel::WALLET();
                $transactionRequest['amount'] = $giftCard->items->sum('price');
                $transactionRequest['narration'] = 'Received gift card';
                $transactionRequest['status'] = TransactionStatus::SUCCESS();
                $transactionRequest['meta'] = json_encode($giftCard);
                $transaction = (new TransactionController())->store($transactionRequest);

                // notify user of transaction
                $request->user()->notify(new Transaction($transaction));

                // notify sender via email, whatsapp or sms
                $giftCard->notify(new Redeemed());

                return $this->show($giftCard);
            });
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }
}
