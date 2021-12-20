<?php

namespace App\Http\Controllers;

use App\Enums\WalletUpdateType;
use App\Http\Requests\SendGiftRequest;
use App\Http\Requests\UpdateGiftRequest;
use App\Mail\GiftMailable;
use App\Models\Getlist;
use App\Models\Gift;
use App\Models\User;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use League\CommonMark\Normalizer\SlugNormalizer;
use Illuminate\Support\Str;

class GiftController extends Controller
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
        $gifts = $request->user()->gifts()->where('getlist_id', 0)->get();

        if ($gifts->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Not found'
            ], 404);
        } else {
            return response()->json([
                'status' => true,
                'data' => $gifts,
                'message' => 'Found'
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Models\Getlist  $getlist
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, Getlist $getlist)
    {
        if ($request->user()->cannot('view', $getlist)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'price' => 'required|numeric',
            'quantity' => 'required|numeric',
            'photo' => 'required|mimes:jpg,jpeg,png',
            'short_message' => 'string',
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

        $request['user_id'] = $request->user()->id;
        $request['getlist_id'] = $getlist->id;
        $request['image'] = $this->cloudinary->upload($request->photo->path(), [
            'folder' => 'getly/gifts/',
            'public_id' => (new SlugNormalizer())->normalize(strtolower($request->name)),
            'overwrite' => true,
            // 'notification_url' => '',
            'resource_type' => 'image'
        ])['secure_url'];
        $request['reference'] = (string) Str::uuid();
        $request['receiver_name'] = $request->user()->name;
        $request['receiver_email'] = $request->user()->email;
        $request['receiver_phone'] = $request->user()->profile->phone;

        return response()->json([
            'status' => true,
            'data' => $this->store($request),
            'message' => 'Success'
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function send(SendGiftRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $receiver = User::where('email', $request->receiver_email)->first();

            if (!$receiver) {
                $request['user_id'] = null;
            } else {
                $request['user_id'] = $receiver->id;
            }

            $request['image_url'] = $this->cloudinary->upload($request->image->path(), [
                'folder' => 'getly/gifts/',
                'public_id' => (new SlugNormalizer())->normalize(strtolower($request->name)),
                'overwrite' => true,
                // 'notification_url' => '',
                'resource_type' => 'image'
            ])['secure_url'];
            $request['sent_by'] =  $request->user()->id;
            $request['reference'] = (string) Str::uuid();
            $request['amount'] =  $request->price;

            $receiver = User::where('email', $request->receiver_email)->first();

            $gift = $this->store($request);

            if ($receiver) {
                $request['summary'] = 'Gift received';
                (new WalletController())->update($request, $receiver->email, 'credit');
            }

            $request['summary'] = 'Gift sent';
            (new WalletController())->update($request, $request->user()->email, 'debit');

            return response()->json([
                'status' => true,
                'data' => $gift,
                'message' => "You’ve just sent your gift to " . $gift->receiver_name,
            ]);
        });
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return Gift::updateOrCreate([
            'user_id' => $request->user_id ?? null,
            'getlist_id' => $request->getlist_id ?? 0,
            'reference' => $request->reference,
            'name' => ucfirst($request->name),
            'price' => $request->price,
            'quantity' => $request->quantity,
            'short_message' => $request->short_message,
            'image_url' =>  $request->image_url,
            'link' => $request->link,
            'receiver_name' => ucfirst($request->receiver_name),
            'receiver_email' => strtolower($request->receiver_email),
            'receiver_phone' => $request->receiver_phone,
            'sent_by' => $request->sent_by ??  $request->user()->id,
        ]);
    }

    public function pendingGifts(Request $request, User $user)
    {
        $gifts = Gift::where(['user_id' => null, 'getlist_id' => 0, 'receiver_email' => $user->email])->get();

        if ($gifts->isEmpty()) {
        } else {
            foreach ($gifts as $gift) {

                DB::transaction(function () use ($gift, $user, $request) {
                    $gift;

                    $gift->update([
                        'user_id' => $user->id,
                    ]);

                    $request['amount'] =  $gift->price;
                    $request['summary'] = 'Gift received';

                    (new WalletController())->update($request, $gift->receiver_email, 'credit');

                    // notify sender through email gift, template , subject
                    // Mail::to($gift->sender->email)->send(new GiftMailable($gift, 'redeemed', 'Gift Received'));
                });
            }
        }
    }

    public function update(UpdateGiftRequest $request, Gift $gift)
    {
        if ($gift->contributorsv) {
            return response()->json([
                'status' => false,
                'message' => 'You can no longer update this gift.',
            ], 422);
        }
        if ($request->user()->cannot('view', $gift)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }

        if ($request->hasFile('photo')) {
            $request['image'] =  $this->cloudinary->upload($request->photo->path(), [
                'folder' => 'getly/gifts/',
                'public_id' => (new SlugNormalizer())->normalize(strtolower($gift->name)),
                'overwrite' => true,
                // 'notification_url' => '',
                'resource_type' => 'image'
            ])['secure_url'];
        }

        $gift->update($request->only(['name', 'short_message', 'image', 'link']));

        return response()->json([
            'status' => true,
            'data' => $gift,
            'message' => 'Success',
        ]);
    }

    public function delete(Request $request, Gift $gift)
    {
        if ($request->user()->cannot('view', $gift)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }

        $gift->delete();

        return response()->json([
            'status' => true,
            'message' => 'Success'
        ]);
    }
}
