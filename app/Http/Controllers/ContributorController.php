<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\StoreContributorRequest;
use App\Models\Contributor;
use App\Models\Gift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContributorController extends Controller
{
    public $reference;

    public function __construct()
    {
        $this->reference = str_shuffle(time() . mt_rand(1000, 9999));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Models\Gift  $gift
     * @param  \App\Http\Requests\StoreContributorRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function fullfill(StoreContributorRequest $request, Gift $gift)
    {
        return DB::transaction(function () use ($request, $gift) {
            $contributed = $gift->contributors->sum('amount');

            if ($contributed >= $gift->price) {
                return response()->json([
                    'status' => false,
                    'message' => "Gift has already been redeemed."
                ], 422);
            }

            if ($request->amount > $gift->price) {
                return response()->json([
                    'status' => false,
                    'message' => "Amount left to contribute is NGN" . number_format($gift->price - $contributed)
                ], 422);
            }

            $total = $gift->price - $contributed;

            if ($request->user()->wallet->balance < $total) {
                return response()->json([
                    'status' => false,
                    'message' => "Insufficient balance"
                ], 422);
            }

            switch ($request->type) {
                case 'contribute':
                    (new TransactionController())->store([
                        'user_id' => $gift->sender->id,
                        'reference' => str_shuffle(time() . mt_rand(1000, 9999)),
                        'provider' => 'getly',
                        'channel' => 'contribution',
                        'amount' => $request->amount,
                        'summary' => 'Gift contribution',
                        'spent' => true,
                        'status' => TransactionType::Success(),
                    ]);

                    $gift->sender->wallet->update([
                        'balance' => $gift->sender->wallet->balance - $request->amount,
                    ]);

                    (new TransactionController())->store([
                        'user_id' => $gift->owner->id,
                        'reference' => str_shuffle(time() . mt_rand(1000, 9999)),
                        'provider' => 'getly',
                        'channel' => 'contribution',
                        'amount' => $request->amount,
                        'summary' => 'Gift contribution',
                        'spent' => false,
                        'status' => TransactionType::Success(),
                    ]);

                    $gift->owner->wallet->update([
                        'balance' => $gift->owner->wallet->balance + $request->amount,
                    ]);

                    break;

                default:

                    (new TransactionController())->store([
                        'user_id' => $gift->sender->id,
                        'reference' => str_shuffle(time() . mt_rand(1000, 9999)),
                        'provider' => 'getly',
                        'channel' => 'purchase',
                        'amount' => $total,
                        'summary' => 'Gift purchase',
                        'spent' => true,
                        'status' => TransactionType::Success(),
                    ]);

                    $gift->sender->wallet->update([
                        'balance' => $gift->sender->wallet->balance - $total,
                    ]);

                    (new TransactionController())->store([
                        'user_id' => $gift->owner->id,
                        'reference' => str_shuffle(time() . mt_rand(1000, 9999)),
                        'provider' => 'getly',
                        'channel' => 'purchase',
                        'amount' => $total,
                        'summary' => 'Gift purchase',
                        'spent' => false,
                        'status' => TransactionType::Success(),
                    ]);

                    $gift->owner->wallet->update([
                        'balance' => $gift->owner->wallet->balance + $total,
                    ]);
                    break;
            }

            Contributor::create([
                'gift_id' => $gift->id,
                'name' => ucfirst($request->name),
                'email' => strtolower($request->email),
                'phone' => $request->phone,
                'amount' => $request->amount,
            ]);

            return response()->json([
                'status' => true,
                'message' => "Payment sent"
            ]);
        });
    }
}
