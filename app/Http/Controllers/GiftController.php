<?php

namespace App\Http\Controllers;

use App\Mail\GiftMailable;
use App\Models\Getlist;
use App\Models\Gift;
use App\Models\User;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
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
            'image' => 'required|mimes:jpg,jpeg,png',
            'link' => 'required|url',
            'receiver_name' => 'required|string',
            'receiver_email' => 'required|email',
            'receiver_phone' => 'required|numeric',
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
        $request['image'] = $this->cloudinary->upload($request->image->path(), [
            'folder' => 'getly/gifts/',
            'public_id' => (new SlugNormalizer())->normalize(strtolower($request->name)),
            'overwrite' => true,
            // 'notification_url' => '',
            'resource_type' => 'image'
        ])['secure_url'];
        $request['reference'] = (string) Str::uuid();

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
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'price' => 'required|numeric',
            'quantity' => 'required|numeric',
            'image' => 'required|mimes:jpg,jpeg,png',
            'link' => 'required|url',
            'receiver_name' => 'required|string',
            'receiver_email' => 'required|email',
            'receiver_phone' => 'required|numeric',
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
        $user = User::where('email', $request->receiver_email)->first();

        if (!$user) {
            $request['user_id'] = null;
        } else {
            $request['user_id'] = $user->id;
        }

        $request['photo'] = $this->cloudinary->upload($request->image->path(), [
            'folder' => 'getly/gifts/',
            'public_id' => (new SlugNormalizer())->normalize(strtolower($request->name)),
            'overwrite' => true,
            // 'notification_url' => '',
            'resource_type' => 'image'
        ])['secure_url'];

        $request['sent_by'] = $request->user()->id;
        $request['amount'] = $request->price;
        $request['customer_email'] = $request->user()->email;
        $request['customer_phone'] = $request->user()->profile->phone;
        $request['customer_name'] = $request->user()->name;
        $request['description'] = $request->name;
        $request['reference'] = (string) Str::uuid();
        $request['redirect_url'] = route('verify-sent-gift', ['gift' => $request->all()]);

        return (new PaymentController())->generateFwPaymentLink($request);
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
            'image' =>  $request->image,
            'link' => $request->link,
            'receiver_name' => ucfirst($request->receiver_name),
            'receiver_email' => strtolower($request->receiver_email),
            'receiver_phone' => $request->receiver_phone,
            'sent_by' => $request->sent_by ??  $request->user()->id,
        ]);
    }

    public function verifySentGift(Request $request)
    {
        $payment = (new PaymentController())->verifyFwPaymentLink($request);

        $gift = collect($request->gift);

        $request['user_id'] = $gift['user_id'] ?? null;
        $request['reference'] = $request->tx_ref . '-' . $request->transaction_id;
        $request['name'] = $gift['name'];
        $request['price'] = $gift['price'];
        $request['quantity'] = $gift['quantity'];
        $request['short_message'] = $gift['short_message'];
        $request['image'] = $gift['photo'];
        $request['link'] = $gift['link'];
        $request['receiver_name'] = $gift['receiver_name'];
        $request['receiver_email'] = $gift['receiver_email'];
        $request['receiver_phone'] = $gift['receiver_phone'];
        $request['sent_by'] = $gift['sent_by'];

        if ($payment['data']['status'] === 'successful') {
            $this->store($request);

            return response()->json([
                'status' => true,
                'data' => $gift,
                'message' => "You’ve just sent your gift to " . $gift['receiver_name'],
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => "Error occured while sending gift, kindly contact support immediately.",
        ], 422);
    }

    public function pendingGifts(User $user)
    {
        $gifts = Gift::where(['user_id' => null, 'getlist_id' => 0, 'receiver_email' => $user->email])->get();
        if ($gifts->isEmpty()) {
        } else {
            foreach ($gifts as $gift) {
                // notify sender through email gift, template , subject
                Mail::to($gift->sent_by['email'])->send(new GiftMailable($gift, 'redeemed', 'Gift Received'));

                $gift->update([
                    'user_id' => $user->id,
                ]);
            }
        }
    }
}
