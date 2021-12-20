<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContributorRequest;
use App\Models\Contributor;
use App\Models\Gift;
use Illuminate\Http\Request;
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
        $contributed = $gift->contributors->sum('amount');

        if ($contributed >= $gift->price) {
            return response()->json([
                'status' => false,
                'message' => "Gift has already been redeemed."
            ], 422);
        } elseif ($request->amount > $gift->price) {
            return response()->json([
                'status' => false,
                'message' => "Amount left to contribute is NGN" . number_format($gift->price - $contributed)
            ], 422);
        } else {

            switch ($request->type) {
                case 'contribute':
                    # code...

                    $request['amount'] = $request->amount;
                    $request['description'] = "Contribution for " . $gift->name;

                    break;

                default:
                    # code...

                    $request['amount'] = $gift->price - $contributed;
                    $request['description'] = "Payment for " . $gift->name;
                    break;
            }

            $request['customer_email'] = $request->email;
            $request['customer_phone'] = $request->phone;
            $request['customer_name'] = $request->name;
            $request['reference'] = (string) Str::uuid();
            $request['redirect_url'] = route('verify-sent-wish', ['gift' => $gift->id]);

            return (new FWController())->generatePaymentLink($request);
        }
    }

    public function verifySentWish(Request $request)
    {
        $payment = (new FWController())->verifyPaymentLink($request);

        if ($payment['data']['status'] === 'successful') {

            Contributor::create([
                'gift_id' => $request->gift,
                'name' => $payment['data']['customer']['name'],
                'email' => $payment['data']['customer']['email'],
                'phone' => $payment['data']['customer']['phone_number'],
                'amount' => $payment['data']['amount'],
            ]);

            return response()->json([
                'status' => true,
                'message' => "Success",
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => "Error occured while sending gift, kindly contact support immediately.",
        ], 422);
    }
}
