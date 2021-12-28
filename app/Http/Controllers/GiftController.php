<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\CreatGiftRequest;
use App\Http\Requests\SendGiftRequest;
use App\Http\Requests\UpdateGiftRequest;
use App\Models\Getlist;
use App\Models\Gift;
use App\Models\User;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use League\CommonMark\Normalizer\SlugNormalizer;

class GiftController extends Controller
{
    private $cloudinary, $reference;

    public function __construct()
    {
        $this->cloudinary = (new UploadApi());
        $this->reference = str_shuffle(time() . mt_rand(1000, 9999));
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
    public function create(CreatGiftRequest $request, Getlist $getlist)
    {
        if ($request->user()->cannot('view', $getlist)) {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ], 403);
        }

        $request['image_url'] = $this->cloudinary->upload($request->photo->path(), [
            'folder' => 'getly/gifts/',
            'public_id' => (new SlugNormalizer())->normalize(strtolower($request->name)),
            'overwrite' => true,
            'resource_type' => 'image'
        ])['secure_url'];

        $gift = $this->store([
            'user_id' => $request->user()->id,
            'getlist_id' => $getlist->id,
            'reference' => $this->reference,
            'name' => $request->name,
            'price' => $request->price,
            'quantity' => $request->quantity,
            'short_message' => $request->short_message,
            'image_url' =>  $request->image_url,
            'link' => $request->link,
            'receiver_name' => $request->user()->name,
            'receiver_email' => $request->user()->email,
            'receiver_phone' => $request->user()->profile->phone,
            'sent_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => true,
            'data' => $gift,
            'message' => 'Gift created'
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

            $gift = $this->store([
                'user_id' => $request->user_id,
                'getlist_id' => 0,
                'reference' => $this->reference,
                'name' => $request->name,
                'price' => $request->price,
                'quantity' => $request->quantity,
                'short_message' => $request->short_message,
                'image_url' =>  $request->image_url,
                'link' => $request->link,
                'receiver_name' => $request->receiver_name,
                'receiver_email' => $request->receiver_email,
                'receiver_phone' => $request->receiver_phone,
                'sent_by' => $request->user()->id,
            ]);

            if ($receiver) {
                (new TransactionController())->store([
                    'user_id' => $receiver->id,
                    'reference' => $this->reference,
                    'provider' => 'getly',
                    'channel' => 'gift',
                    'amount' => $request->price,
                    'summary' => 'Gift received from ' . $request->user()->name,
                    'spent' => false,
                    'status' => TransactionType::Success(),
                ]);

                $receiver->wallet->update([
                    'balance' => $receiver->wallet->balance + $request->price,
                ]);
            }

            (new TransactionController())->store([
                'user_id' => $request->user()->id,
                'reference' => str_shuffle(time() . mt_rand(1000, 9999)),
                'provider' => 'getly',
                'channel' => 'gift',
                'amount' => $request->price,
                'summary' => 'Gift sent to ' . $request->receiver_name,
                'spent' => true,
                'status' => TransactionType::Success(),
            ]);

            $request->user()->wallet->update([
                'balance' => $request->user()->wallet->balance - $request->price,
            ]);

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
     * @return \Illuminate\Http\Response
     */
    public function store($gift)
    {
        return Gift::updateOrCreate([
            'user_id' => $gift['user_id'],
            'getlist_id' => $gift['getlist_id'],
            'reference' => $gift['reference'],
            'name' => ucfirst($gift['name']),
            'price' => $gift['price'],
            'quantity' => $gift['quantity'],
            'short_message' => $gift['short_message'],
            'image_url' =>  $gift['image_url'],
            'link' => $gift['link'],
            'receiver_name' => ucfirst($gift['receiver_name']),
            'receiver_email' => strtolower($gift['receiver_email']),
            'receiver_phone' => $gift['receiver_phone'],
            'sent_by' => $gift['sent_by'],
        ]);
    }

    public function pendingGifts(Request $request, User $user)
    {
        $gifts = Gift::where(['user_id' => null, 'getlist_id' => 0, 'receiver_email' => $user->email])->get();

        if ($gifts->isEmpty()) {
        } else {
            foreach ($gifts as $gift) {

                DB::transaction(function () use ($gift, $user, $request) {
                    $gift->update([
                        'user_id' => $user->id,
                    ]);

                    (new TransactionController())->store([
                        'user_id' => $request->user()->id,
                        'reference' => $this->reference,
                        'provider' => 'getly',
                        'channel' => 'gift',
                        'amount' => $request->price,
                        'summary' => 'Gift received from ' . $gift->sender->name,
                        'spent' => false,
                        'status' => TransactionType::Success(),
                    ]);

                    $request->user()->wallet->update([
                        'balance' => $request->user()->wallet->balance + $request->price,
                    ]);

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

        $gift->update($request->only(['short_message', 'image', 'link']));

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
