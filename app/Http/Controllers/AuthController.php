<?php

namespace App\Http\Controllers;

use App\Enums\OTPType;
use App\Models\Profile;
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
            'phone_code' => 'required|integer',
            'phone' => ['required', 'digits:10', function ($attribute, $value, $fail) use ($request) {
                if (Profile::where('phone', $request->phone_code . $request->phone)->first()) {
                    return $fail(__('Phone number has already been taken.'));
                }
            }],
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
            // (new WalletController())->store($request);

            $request['user'] = $user;
            $request['code'] = Str::random(40);
            $request['type'] = OTPType::EmailVerification();
            $request['email_template'] = 'email_verification';
            (new OTPController())->send($request);

            $request['message'] = trans('auth.signup');
            $request['status_code'] = 201;

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

        if (!$user->email_verified_at) {
            $request['user'] = $user;
            $request['code'] = Str::random(40);
            $request['type'] = OTPType::EmailVerification();
            $request['email_template'] = 'email_verification';
            (new OTPController())->send($request);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'token' => $user->createToken($request->device_name)->plainTextToken,
            ],
            'message' => $request->message ?? trans('auth.signin'),
        ], $request->status_code ?? 200);
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

        return DB::transaction(function () use ($user, $request) {
            $verify =  (new OTPController())->verify($request);

            if ($verify->original['status']) {
                $user->update([
                    'email_verified_at' => now(),
                ]);

                (new GiftController())->pendingGifts($user);

                return $verify;
            } else {
                return $verify;
            }
        });
    }

    //set-pin
    public function setPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin' => ['required', 'digits:4', function ($attribute, $value, $fail) use ($request) {
                if ($request->user()->profile->password) {
                    return $fail(__('Your pin has already been set.'));
                }
            }],
            'pin_confirmation' => 'same:pin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $request->user()->profile->update([
            'password' => Hash::make($request->pin),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Success',
        ]);
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
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user = User::where('email', $request->email)->first()) {
            return response()->json([
                'status' => false,
                'message' => trans('auth.failed'),
            ], 404);
        }

        $request['user'] = $user;
        $request['code'] = Str::random(40);
        $request['type'] = OTPType::PasswordReset();
        $request['email_template'] = 'forgot_password';

        (new OTPController())->send($request);

        return response()->json([
            'status' => true,
            'data' => [
                'email' => $user->email
            ],
            'message' => trans('passwords.sent'),
        ]);
    }

    // reset-password
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed',
            'type' => 'required|in:password_reset',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if (!$user = User::where('email', $request->email)->first()) {
            return response()->json([
                'status' => false,
                'message' => trans('auth.failed'),
            ], 404);
        }

        $request['user'] = $user;
        $request['email_template'] = 'password_reset';

        $verify =  (new OTPController())->verify($request);

        if ($verify->original['status']) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            return $verify;
        } else {
            return $verify;
        }

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
