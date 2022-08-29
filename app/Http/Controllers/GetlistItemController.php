<?php

namespace App\Http\Controllers;

use App\Enums\GetlistItemContributionType;
use App\Enums\GetlistItemStatus;
use App\Enums\TransactionChannel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\StoreGetlistItemContributorRequest;
use App\Http\Requests\StoreGetlistItemRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateGetlistItemRequest;
use App\Models\GetlistItem;
use App\Models\GetlistItemContributor;
use App\Notifications\Contribution;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class GetlistItemController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreGetlistItemRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreGetlistItemRequest $request)
    {

        // set status
        $request['status'] = GetlistItemStatus::UNFULFILLED();

        // upload image
        $request['image_url'] = (new UploadApi())->upload($request->image->path(), [
            'folder' => config('app.name') . '/gifts/',
            'public_id' => str_shuffle($request->getlist_id . rand(000000, 999999)),
            'overwrite' => true,
            // 'notification_url' => '',
            'resource_type' => 'image'
        ])['secure_url'];

        $getlistItem = GetlistItem::create($request->only([
            'getlist_id',
            'name',
            'price',
            'quantity',
            'details',
            'image_url',
            'status'
        ]));

        return $this->show($getlistItem, null, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\GetlistItem  $getlistItem
     * @return \Illuminate\Http\Response
     */
    public function show(GetlistItem $getlistItem, $message = 'success', $code = 200)
    {
        // add contributos to data
        $getlistItem->contributors;

        return response()->json([
            'status' => true,
            'data' => $getlistItem,
            'message' => $message,
        ], $code);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateGetlistItemRequest  $request
     * @param  \App\Models\GetlistItem  $getlistItem
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateGetlistItemRequest $request, GetlistItem $getlistItem)
    {
        // upload image
        if ($request->hasFile('image')) {
            $request['image_url'] = (new UploadApi())->upload($request->image->path(), [
                'folder' => config('app.name') . '/gifts/',
                'public_id' => $getlistItem->id,
                'overwrite' => true,
                // 'notification_url' => '',
                'resource_type' => 'image'
            ])['secure_url'];
        }

        // update details
        $getlistItem->update($request->only([
            'name',
            'details',
            'image_url'
        ]));

        return $this->show($getlistItem);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\GetlistItem  $getlistItem
     * @return \Illuminate\Http\Response
     */
    public function destroy(GetlistItem $getlistItem)
    {
        // restore getlist item if trashed or deleted it
        if ($getlistItem->trashed()) {
            $getlistItem->restore();
        } else {
            $getlistItem->delete();
        }

        return $this->show($getlistItem);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\StoreGetlistItemContributorRequest  $request
     * @param  \App\Models\GetlistItem  $getlistItem
     * @return \Illuminate\Http\Response
     */
    public function contribute(StoreGetlistItemContributorRequest $request, GetlistItem $getlistItem)
    {
        try {
            return DB::transaction(function () use ($request, $getlistItem) {
                // checks if unfufilled
                if (!$getlistItem->status->is(GetlistItemStatus::UNFULFILLED())) {
                    throw ValidationException::withMessages([
                        'This gift is either ' . GetlistItemStatus::REDEEMABLE() . ' or has already been ' . GetlistItemStatus::CLAIMED() . '.'
                    ]);
                }

                // get amount
                $amount = match ($request->type) {
                    'contribute' => $request->amount,
                    default => $getlistItem->price - $getlistItem->contributors->sum('amount')
                };

                // generate payment link
                $request['tx_ref'] = Str::uuid();
                $request['name'] = $request->full_name;
                $request['email'] = $request->email_address;
                $request['phone'] = $request->phone_number;
                $request['amount'] = $amount;
                $request['meta'] = [
                    "consumer_id" => $getlistItem->id,
                    "consumer_mac" => $request->type,
                ];
                $request['redirect_url'] = url("/contribution?gift={$getlistItem->id}");

                $link = (new FlutterwaveController())->generatePaymentLink($request->all());

                // set payment link
                $getlistItem->payment_link = $link['data']['link'];

                return $this->show($getlistItem);
            });
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'message' => $th->getMessage()
            ]);
        }
    }

    public function webHook($data)
    {
        try {
            // find getlist item
            if (!$getlistItem = GetlistItem::find($data['data']['meta']['consumer_id'])) {
                return response()->json([], 422);
            }

            // checks duplicate entry
            if (GetlistItemContributor::where('reference', $data['data']['tx_ref'])->first()) {
                return response()->json([], 422);
            }

            // get transaction status
            $status = match ($data['data']['transaction'] ? $data['data']['transaction']['status'] : $data['data']['status']) {
                'success' => TransactionStatus::SUCCESS(),
                'successful' => TransactionStatus::SUCCESS(),
                'new' => TransactionStatus::SUCCESS(),
                'pending' => TransactionStatus::SUCCESS(),
                default => TransactionStatus::FAILED()
            };

            // update getlist status
            if ($data['type'] === GetlistItemContributionType::BUY()) {
                $getlistItem->update([
                    'status' => GetlistItemStatus::REDEEMABLE()
                ]);
            }

            // set channel
            $channel = match ($data['data']['meta']['consumer_mac']) {
                'buy' => TransactionChannel::GETLIST_PURCHASE(),
                default => TransactionChannel::GETLIST_CONTRIBUTION()
            };

            // update getlist status
            if ($channel === TransactionChannel::GETLIST_PURCHASE()) {
                $getlistItem->update([
                    'status' => GetlistItemStatus::REDEEMABLE()
                ]);
            }

            // update transaction if exists
            if ($data['data']['transaction']) {
                $data['data']['transaction']->update([
                    'status' => $status
                ]);
            } else {
                $transaction = (new StoreTransactionRequest());
                $transaction['user_id'] = $getlistItem->getlist->user->id;
                $transaction['identity'] = $data['data']['tx_ref'];
                $transaction['reference'] = $data['data']['flw_ref'];
                $transaction['type'] = TransactionType::CREDIT();
                $transaction['channel'] = $channel;
                $transaction['amount'] = $data['data']['amount'];
                $transaction['narration'] = $data['data']['narration'];
                $transaction['status'] = $status;
                $transaction['meta'] = json_encode($data);
            }


            // store contributor details
            $storeGetlistItemContributorRequest = (new StoreGetlistItemContributorRequest($data));
            $storeGetlistItemContributorRequest['getlist_item_id'] = $getlistItem->id;
            $storeGetlistItemContributorRequest['reference'] = $data['data']['tx_ref'];
            $storeGetlistItemContributorRequest['full_name'] = $data['data']['customer']['name'];
            $storeGetlistItemContributorRequest['email_address'] = $data['data']['customer']['email'];
            $storeGetlistItemContributorRequest['phone_number'] = $data['data']['customer']['phone_number'];
            $storeGetlistItemContributorRequest['type'] =  $data['data']['meta']['consumer_mac'];
            $storeGetlistItemContributorRequest['amount'] = $data['data']['amount'];
            $storeGetlistItemContributorRequest['meta'] = json_encode($storeGetlistItemContributorRequest);
            $contributor = (new GetlistItemContributorController())->store($storeGetlistItemContributorRequest);

            // credit wallet if success
            if ($status === TransactionStatus::SUCCESS()) {
                $getlistItem->getlist->user->wallet->credit($data['data']['amount']);
            }

            // notify gift owner
            $getlistItem->getlist->user->notify(new Contribution($getlistItem, $contributor));
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([$th->getMessage()]);
        }
    }
}
