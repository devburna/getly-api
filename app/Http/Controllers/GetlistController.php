<?php

namespace App\Http\Controllers;

use App\Models\Getlist;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use League\CommonMark\Normalizer\SlugNormalizer;

class GetlistController extends Controller
{
    private $cloudinary;

    public function __construct()
    {
        $this->cloudinary = (new UploadApi());
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $getlists = $request->user()->getlists;

        if ($getlists->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Not found'
            ], 404);
        } else {
            return response()->json([
                'status' => true,
                'data' => $getlists,
                'message' => 'Found'
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|mimes:jpg,jpeg,png',
            'title ' => 'required|string|max:50',
            'event_date' => 'required|datetime',
            'privacy' => 'required|boolean'
        ]);

        if ($request->has('short_message')) {
            $validator = Validator::make($request->all(), [
                'short_message' => 'required|string|max:100',
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $getlist = Getlist::create([
            'user_id' => $request->user()->id,
            'image' =>  $this->cloudinary->upload($request->image->path(), [
                'folder' => 'getly/getlists/',
                'public_id' => (new SlugNormalizer())->normalize(strtolower($request->title)),
                'overwrite' => true,
                // 'notification_url' => '',
                'resource_type' => 'image'
            ])['secure_url'],
            'title' => ucfirst($request->title),
            'event_date' => $request->event_date,
            'short_message' => $request->short_message,
            'privacy' => $request->privacy,
        ]);

        return response()->json([
            'status' => true,
            'data' => $getlist,
            'message' => 'Success'
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Getlist  $getlist
     * @return \Illuminate\Http\Response
     */
    public function show(Getlist $getlist)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Getlist  $getlist
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Getlist $getlist)
    {

        if ($request->has('title')) {
            $validator = Validator::make($request->all(), [
                'title ' => 'string|max:50',
            ]);

            $request['title'] = ucfirst($request->title);
        }

        if ($request->has('event_date')) {
            $validator = Validator::make($request->all(), [
                'event_date' => 'datetime',
            ]);
        }

        if ($request->has('short_message')) {
            $validator = Validator::make($request->all(), [
                'short_message' => 'string|max:100',
            ]);
        }

        if ($request->has('privacy')) {
            $validator = Validator::make($request->all(), [
                'privacy' => 'boolean'
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $getlist->update($request->all());

        return response()->json([
            'status' => true,
            'data' => $getlist,
            'message' => 'Success'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Getlist  $getlist
     * @return \Illuminate\Http\Response
     */
    public function destroy(Getlist $getlist)
    {
        //
    }
}
