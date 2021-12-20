<?php

namespace App\Http\Controllers;

use App\Enums\OTPType;
use App\Http\Requests\ForgotPwdRequest;
use App\Http\Requests\ResetPwdRequest;
use App\Http\Requests\SetPinRequest;
use App\Http\Requests\SigninRequest;
use App\Http\Requests\SignupRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Http\Requests\VerifyPinRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // register
    public function register(SignupRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => ucfirst($request->full_name),
                'email' => strtolower($request->email),
                'password' => Hash::make($request->password),
            ]);

            $request['user_id'] = $user->id;

            (new WalletController())->create($request);
            (new ProfileController())->store($request);

            $request['user'] = $user;
            $request['code'] = Str::random(40);
            $request['type'] = OTPType::EmailVerification();
            $request['email_template'] = 'email_verification';

            (new OTPController())->send($request);

            return response()->json([
                'status' => true,
                'data' => [
                    'token' => $user->createToken($request->device_name)->plainTextToken,
                ],
                'message' => trans('auth.signup'),
            ], 201);
        });
    }

    // login
    public function login(SigninRequest $request)
    {
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
    public function verifyEmail(VerifyEmailRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        $request['user'] = $user;
        $request['email_template'] = 'email_verified';

        return DB::transaction(function () use ($user, $request) {
            $verify =  (new OTPController())->verify($request);

            if ($verify->original['status']) {
                $user->update([
                    'email_verified_at' => now(),
                ]);

                (new GiftController())->pendingGifts($request, $user);

                return $verify;
            } else {
                return $verify;
            }
        });
    }

    //set-pin
    public function setPin(SetPinRequest $request)
    {
        $request->user()->profile->update([
            'password' => Hash::make($request->pin),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Success',
        ]);
    }

    // verify-pin
    public function verifyPin(VerifyPinRequest $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'Success',
        ]);
    }

    // forgot-password
    public function forgotPwd(ForgotPwdRequest $request)
    {
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
    public function resetPwd(ResetPwdRequest $request)
    {
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
