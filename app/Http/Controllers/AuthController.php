<?php

namespace App\Http\Controllers;

use App\Enums\OTPType;
use App\Mail\OTPMailable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // register
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:50|unique:users,name',
            'email' => 'required|email|unique:users,email',
            'country_code' => 'required|integer',
            'phone' => 'required|digits:10',
            'birthday' => 'required|date|before:today',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => ucfirst($request->full_name),
                'email' => strtolower($request->email),
                'password' => Hash::make($request->password),
            ]);

            $request['user_id'] = $user->id;

            (new ProfileController())->store($request);

            $request['user'] = $user;
            $request['email'] = $user->email;
            $request['code'] = Str::random(40);
            $request['type'] = OTPType::EmailVerification();
            $request['email_template'] = 'email_verification';
            (new OTPController())->send($request);

            $request['message'] = trans('auth.signup');
            $request['code'] = 201;

            return $this->login($request);
        });
    }

    // login
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => trans('auth.failed'),
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'token' => $user->createToken($request->device_name)->plainTextToken,
            ],
            'message' => $request->message ?? trans('auth.signin'),
        ], $request->code ?? 200);
    }

    // verify-email
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required',
            'type' => 'required|in:email_verification',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        $request['user'] = $user;
        $request['email_template'] = 'email_verified';

        $verify =  (new OTPController())->verify($request);

        if ($verify->original['status']) {
            $user->update([
                'email_verified_at' => now(),
            ]);

            return $verify;
        } else {
            return $verify;
        }
    }

    // verify-pin
    public function verifyPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin' => ['required', function ($attribute, $value, $fail) use ($request) {
                if (!Hash::check($value, $request->user()->profile->password)) {
                    return $fail(__('Your pin is incorrect.'));
                }
            }],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Success',
        ]);
    }

    // forgot-password
    public function recover(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => [
                'email' => $request->email
            ],
            'message' => trans('passwords.sent'),
        ]);
    }

    // reset-password
    public function reset(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => trans('passwords.reset'),
        ]);
    }

    // logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Success',
        ]);
    }
}
