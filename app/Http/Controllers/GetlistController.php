<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGetlistRequest;
use App\Http\Requests\UpdateGetlistRequest;
use App\Models\Getlist;
use Illuminate\Http\Request;
use Cloudinary\Api\Upload\UploadApi;

class GetlistController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!$request->privacy) {
            // public getlists
            $getlists = Getlist::where('privacy', false)->orderByDesc('created_at')->with('items.contributors')->paginate(20);
        } else {
            // user's getlists
            $getlists = $request->user()->getlists()->orderByDesc('created_at')->with('items.contributors')->paginate(20);
        }

        // filter result
        if ($request->filter) {
            $getlists = $getlists->where('status', $request->filter);
        }

        foreach ($getlists as $getlist) {

            // add item count to data as wishes
            $getlist->wishes = $getlist->items->count();
        }

        return response()->json([
            'status' => true,
            'data' => $getlists,
            'message' => 'success',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreGetlistRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreGetlistRequest $request)
    {
        // set user id
        $request['user_id'] = $request->user()->id;

        // upload image
        $request['image_url'] = (new UploadApi())->upload($request->image->path(), [
            'folder' => config('app.name') . '/getlists/',
            'public_id' => str_shuffle($request->user_id . rand(000000, 999999)),
            'overwrite' => true,
            // 'notification_url' => '',
            'resource_type' => 'image'
        ])['secure_url'];

        $getlist = Getlist::create($request->only([
            'user_id',
            'title',
            'event_date',
            'message',
            'privacy',
            'image_url',
        ]));

        return $this->show($getlist, null, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Getlist  $getlist
     * @return \Illuminate\Http\Response
     */
    public function show(Getlist $getlist, $message = 'success', $code = 200)
    {
        // add item count to data as wishes
        $getlist->wishes = $getlist->items->count();

        // add contributors to data
        $getlist->items()->with('contributors');

        return response()->json([
            'status' => true,
            'data' => $getlist,
            'message' => $message,
        ], $code);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateGetlistRequest  $request
     * @param  \App\Models\Getlist  $getlist
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateGetlistRequest $request, Getlist $getlist)
    {
        // upload image
        if ($request->hasFile('image')) {
            $request['image_url'] = (new UploadApi())->upload($request->image->path(), [
                'folder' => config('app.name') . '/getlists/',
                'public_id' => $getlist->id,
                'overwrite' => true,
                // 'notification_url' => '',
                'resource_type' => 'image'
            ])['secure_url'];
        }

        // update details
        $getlist->update($request->only([
            'title',
            'event_date',
            'message',
            'privacy',
            'image_url',
        ]));

        return $this->show($getlist);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Getlist  $getlist
     * @return \Illuminate\Http\Response
     */
    public function destroy(Getlist $getlist)
    {
        // restore getlist if trashed or deleted it
        if ($getlist->trashed()) {
            $getlist->restore();
        } else {
            $getlist->delete();
        }

        return $this->show($getlist);
    }
}
