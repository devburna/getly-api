<?php

namespace App\Http\Controllers;

use App\Models\Getlist;
use App\Models\Gift;
use App\Models\User;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
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
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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

        if (!$user = User::where('email', $request->receiver_email)->first()) {
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
        $request['sender_id'] = $request->user()->id;
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
            'receiver_name' => ucfirst($request->name),
            'receiver_email' => strtolower($request->receiver_email),
            'receiver_phone' => $request->receiver_phone,
        ]);
    }

    public function verifySentGift(Request $request)
    {

        $payment = (new PaymentController())->verifyFwPaymentLink($request);

        $gift = collect($request->gift);

        $request['sender'] = $gift['sender_id'];
        $request['reference'] = $gift['reference'];
        $request['name'] = $gift['name'];
        $request['price'] = $gift['price'];
        $request['quantity'] = $gift['quantity'];
        $request['short_message'] = $gift['short_message'];
        $request['image'] = $gift['photo'];
        $request['link'] = $gift['link'];
        $request['receiver_name'] = $gift['receiver_name'];
        $request['receiver_email'] = $gift['receiver_email'];
        $request['receiver_phone'] = $gift['receiver_phone'];

        if ($payment['data']['status'] === 'successful') {
            $this->store($request);
        }

        return view('welcome', [
            'payment' =>  $payment,
        ]);
    }
}
