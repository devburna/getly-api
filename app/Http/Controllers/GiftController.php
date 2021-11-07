<?php

namespace App\Http\Controllers;

use App\Models\Getlist;
use App\Models\Gift;
use App\Models\User;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\CommonMark\Normalizer\SlugNormalizer;

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

        $request['redirect_url'] = '';
        $request['amount'] = $request->price;
        $request['customer_email'] = $request->user()->email;
        $request['customer_phone'] = $request->user()->profile->phone;
        $request['customer_name'] = $request->user()->name;

        return response()->json([
            'status' => true,
            'data' => [
                'payment_link' => $this->generatePaymentLink($request),
            ],
            'message' => 'Success'
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return Gift::create([
            'user_id' => $request->user_id ?? null,
            'getlist_id' => $request->getlist_id ?? 0,
            'name' => ucfirst($request->name),
            'price' => $request->price,
            'quantity' => $request->quantity,
            'short_message' => $request->short_message,
            'image' =>  $this->cloudinary->upload($request->image->path(), [
                'folder' => 'getly/gifts/',
                'public_id' => (new SlugNormalizer())->normalize(strtolower($request->name)),
                'overwrite' => true,
                // 'notification_url' => '',
                'resource_type' => 'image'
            ])['secure_url'],
            'link' => $request->link,
            'receiver_name' => ucfirst($request->name),
            'receiver_email' => strtolower($request->receiver_email),
            'receiver_phone' => $request->receiver_phone,
        ]);
    }

    public function generatePaymentLink(Request $request)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ',
        ])->post('https://api.flutterwave.com/v3/payments', [
            'tx_ref' => Str::random(15),
            'amount' => $request->amount,
            'currency' => 'NGN',
            'redirect_url' => $request->redirect_url,
            'meta' => [
                'consumer_id' => 23,
                'consumer_mac' => '92a3-912ba-1192a',
                'item' => $request
            ],
            'customer' => [
                'email' => strtolower($request->customer_email),
                'phonenumber' => $request->customer_phone,
                'name' => ucfirst($request->customer_name),
            ],
            'customizations' => [
                'title' => config('app.name'),
                'description' => ucfirst($request->description),
                'logo' => asset('img/logo.png'),
            ],
        ])->json();
    }

    public function notifyWhatsapp()
    {
        Http::post('https://api.whatsapp.com/send?phone=2348118579390&text=Hello%20World');
    }
}
