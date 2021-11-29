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
        $received_gifts = $request->user()->gifts;

        $lists = [];

        foreach ($getlists as $getlist) {
            # code...
            $list = [
                'id' => $getlist->id,
                'image_url' => $getlist->image,
                'title' => $getlist->title,
                'event_date' => $getlist->event_date,
                'short_message' => $getlist->short_message,
                'privacy' => $getlist->privacy,
                'item_count' => $getlist->gifts ? $getlist->gifts->count() : 0,
                'created' => $getlist->created_at,
            ];

            array_push($lists, $list);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'getlists' => $lists,
                'received_gifts_count' => $received_gifts ? $received_gifts->count() : 0,
            ],
            'message' => 'Found'
        ]);
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
            // 'image' => 'required|mimes:jpg,jpeg,png',
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
            // 'image' =>  $this->cloudinary->upload($request->image->path(), [
            //     'folder' => 'getly/getlists/',
            //     'public_id' => (new SlugNormalizer())->normalize(strtolower($request->title)),
            //     'overwrite' => true,
            //     // 'notification_url' => '',
            //     'resource_type' => 'image'
            // ])['secure_url'],
            'image' => 'none',
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
    public function show(Request $request, Getlist $getlist)
    {
        if ($request->user()->cannot('view', $getlist)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $getlist->id,
                'image_url' => $getlist->image,
                'title' => $getlist->title,
                'event_date' => $getlist->event_date,
                'short_message' => $getlist->short_message,
                'privacy' => $getlist->privacy,
                'items' => $getlist->gifts,
                'created' => $getlist->created_at,
            ],
            'message' => 'Found'
        ]);
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
        if ($request->user()->cannot('update', $getlist)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }

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
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Getlist  $getlist
     * @return \Illuminate\Http\Response
     */
    public function updateImage(Request $request, Getlist $getlist)
    {
        if ($request->user()->cannot('update', $getlist)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|mimes:jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $getlist->update([
            'image' =>  $this->cloudinary->upload($request->image->path(), [
                'folder' => 'getly/getlists/',
                'public_id' => (new SlugNormalizer())->normalize(strtolower($getlist->title)),
                'overwrite' => true,
                // 'notification_url' => '',
                'resource_type' => 'image'
            ])['secure_url'],
        ]);

        return response()->json([
            'status' => true,
            'data' => $getlist,
            'message' => 'Success'
        ]);
    }
}
