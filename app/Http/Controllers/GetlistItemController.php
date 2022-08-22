<?php

namespace App\Http\Controllers;

use App\Enums\GetlistItemContributionType;
use App\Enums\GetlistItemStatus;
use App\Http\Requests\StoreGetlistItemContributorRequest;
use App\Http\Requests\StoreGetlistItemRequest;
use App\Http\Requests\UpdateGetlistItemRequest;
use App\Models\GetlistItem;
use App\Notifications\Contribution;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        return DB::transaction(function () use ($request, $getlistItem) {

            // checks if user has funds
            if (!$request->user()->hasFunds($request->meta['contribute']['amount'])) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient funds, please fund wallet and try again.'
                ]);
            }

            // checks if unfufilled
            if (!$getlistItem->status->is(GetlistItemStatus::UNFULFILLED())) {
                throw ValidationException::withMessages([
                    'amount' => 'This gift is either ' . GetlistItemStatus::REDEEMABLE() . ' or has already been ' . GetlistItemStatus::CLAIMED() . '.',
                ]);
            }

            // get amount
            if ($request->type->is(GetlistItemContributionType::CONTRIBUTE())) {
                $amount = $request->meta->contribute->amount;
            } else {
                $amount = $request->meta->contribute->amount;

                // update status to fulfilled
                $getlistItem->update([
                    'status' => GetlistItemStatus::REDEEMABLE(),
                ]);
            }

            return $request->all();

            // store contributor
            $request['getlist_item_id'] = $getlistItem->id;
            (new GetlistItemContributorController())->store($request);

            // credit gift owner
            $getlistItem->getlist->user->credit($amount);

            // debit contributor
            $request->user()->debit($amount);

            // notify gift owner
            $getlistItem->getlist->user->notify(new Contribution($getlistItem));

            return $this->show($getlistItem);
        });
    }
}
