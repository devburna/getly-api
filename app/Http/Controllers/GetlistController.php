<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGetlistRequest;
use App\Http\Requests\UpdateGetlistRequest;
use App\Models\Getlist;
use Illuminate\Http\Request;

class GetlistController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->public) {
            $getlists = Getlist::where('privacy', false)->orderByDesc('created_at')->paginate(20);
        } else {
            $getlists = $request->user()->getlists()->orderByDesc('created_at')->paginate(20);
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

        $getlist = Getlist::create($request->only([
            'user_id',
            'title',
            'event_date',
            'message',
            'privacy',
        ]));

        return $this->show($getlist);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Getlist  $getlist
     * @return \Illuminate\Http\Response
     */
    public function show(Getlist $getlist, $message = 'success', $code = 200)
    {
        // add getlist items to data
        // $getlist->owner;

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
        // update details
        $getlist->update($request->only([
            'title',
            'event_date',
            'message',
            'privacy',
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
