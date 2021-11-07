<?php

namespace App\Http\Controllers;

use App\Mail\OTPMailable;
use App\Models\OTP;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OTPController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function send(Request $request)
    {
        $otp = OTP::create([
            'email' => $request->email,
            'token' => Hash::make($request->code),
            'type' => $request->otp_type,
            'expired_at' => Carbon::parse(now())->addHour()
        ]);

        $request['subject'] = str_replace('_', ' ', ucfirst($request->otp_type));
        $request['otp'] = $otp;

        Mail::to($request->email)->send(new OTPMailable($request));

        if ($request->message)
            return response()->json([
                'status' => true,
                'message' => $request->message,
            ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\OTP  $oTP
     * @return \Illuminate\Http\Response
     */
    public function destroy(OTP $oTP)
    {
        //
    }
}
