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

    public function send(Request $request)
    {
        $otp = OTP::where(['email' => $request->email, 'type' => $request->type])->first();

        if (!$otp || Carbon::parse($otp->expired_at)->isPast()) {

            $otp = OTP::create([
                'email' => $request->email,
                'token' => Hash::make($request->code),
                'type' => $request->type,
                'expired_at' => Carbon::parse(now())->addMinutes(30)
            ]);

            $request['subject'] = str_replace('_', ' ', ucfirst($request->type));
            $request['otp'] = $otp;

            Mail::to($request->email)->send(new OTPMailable($request));
        }

        if ($request->message)
            return response()->json([
                'status' => true,
                'message' => $request->message,
            ]);
    }

    public function verify(Request $request)
    {
        $otp = OTP::where(['email' => $request->email, 'type' => $request->type])->first();

        if (!$otp || !Hash::check($request->token, $otp->token)) {
            return response()->json([
                'status' => false,
                'message' => str_replace('_', ' ', ucfirst($request->type)) . ' token is invalid.',
            ], 404);
        }

        if (Carbon::parse($otp->expired_at)->isPast()) {
            $otp->delete();

            return response()->json([
                'status' => false,
                'message' => str_replace('_', ' ', ucfirst($request->type)) . ' token has expired.',
            ], 403);
        }

        $otp->delete();

        $request['subject'] = str_replace('_', ' ', ucfirst($request->email_template));

        Mail::to($request->email)->send(new OTPMailable($request));

        return response()->json([
            'status' => true,
            'message' => str_replace('_', ' ', ucfirst($request->type)) . ' was successfull.',
        ]);
    }
}
