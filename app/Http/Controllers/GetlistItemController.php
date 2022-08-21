<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGetlistItemRequest;
use App\Http\Requests\UpdateGetlistItemRequest;
use App\Models\GetlistItem;
use Cloudinary\Api\Upload\UploadApi;

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
            'image_url'
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
            'price',
            'quantity',
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
}
