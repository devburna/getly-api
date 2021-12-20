<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContributorRequest;
use App\Models\Contributor;
use App\Models\Gift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContributorController extends Controller
{
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

            switch ($request->type) {
                case 'contribute':
                    $request['summary'] = 'Gift contribution';
                    (new WalletController())->update($request, $gift->receiver_email, 'credit');
                    (new WalletController())->update($request, $request->user()->email, 'debit');

                    break;

                default:
                    $request['amount'] = $gift->price - $contributed;;
                    $request['summary'] = 'Gift purchase';
                    (new WalletController())->update($request, $gift->receiver_email, 'credit');
                    (new WalletController())->update($request, $request->user()->email, 'debit');
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
