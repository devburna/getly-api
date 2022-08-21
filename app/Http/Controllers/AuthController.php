<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailVerificationRequest;
use App\Http\Requests\PhoneVerificationRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SigninRequest;
use App\Http\Requests\SignupRequest;
use App\Http\Requests\StoreWalletRequest;
use App\Models\User;
use App\Notifications\EmailVerification;
use App\Notifications\ForgotPassword;
use App\Notifications\PhoneVerification;
use App\Notifications\ResetPassword;
use App\Notifications\Welcome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // register
    public function register(SignupRequest $request)
    {
        return DB::transaction(function () use ($request) {

            // secure password
            $request['password'] = Hash::make($request->password);

            // store new user
            $user = (new UserController())->store($request);

            // send email verification link to user
            $user->notify(new EmailVerification($user->createToken('email-verification', ['verify-email-address'])->plainTextToken));

            return response()->json([
                'status' => true,
                'data' => $user,
                'message' => 'success',
            ], 201);
        });
    }

    // login
    public function login(SigninRequest $request)
    {
        $user = User::where('email_address', $request->email_address)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => trans('auth.failed'),
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'token' => $user->createToken('login', ['authenticate'])->plainTextToken
            ],
            'message' => 'success',
        ]);
    }

    // email verification
    public function emailVerification(Request $request)
    {
        // check if email address has been verified
        if ($request->user()->email_address_verified_at) {
            throw ValidationException::withMessages([
                'email_address' => trans('auth.email_verified')
            ]);
        }

        // verify user email address
        $request->user()->update([
            'email_address_verified_at' => now()
        ]);

        // welcome user
        $request->user()->notify(new Welcome());

        // delete email verification token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'data' => [
                'email_address' => $request->user()->email_address
            ],
            'message' => 'success',
        ]);
    }

    // resend email verification link
    public function resendEmailVerificationLink(EmailVerificationRequest $request)
    {
        // verify user email address
        if (!$user = User::where('email_address', $request->email_address)->first()) {
            return response()->json([
                'status' => false,
                'message' => trans('auth.failed'),
            ], 404);
        }

        // check if email address has been verified
        if ($user->email_address_verified_at) {
            throw ValidationException::withMessages([
                'email_address' => trans('auth.email_verified')
            ]);
        }

        // send email verification link to user
        $user->notify(new EmailVerification($user->createToken('email-verification', ['verify-email-address'])->plainTextToken));

        return response()->json([
            'status' => true,
            'data' => [
                'email_address' => $user->email_address
            ],
            'message' => 'success',
        ]);
    }

    // phone verification
    public function phoneVerification(Request $request)
    {
        // check if phone number has been verified
        if ($request->user()->phone_number_verified_at) {
            throw ValidationException::withMessages([
                'phone_number' => trans('auth.phone_verified')
            ]);
        }

        // verify user phone number
        $request->user()->update([
            'phone_number_verified_at' => now()
        ]);

        // delete phone verification token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'data' => [
                'phone_number' => $request->user()->phone_number
            ],
            'message' => 'success',
        ]);
    }

    // resend phone verification link
    public function resendPhoneVerificationLink(PhoneVerificationRequest $request)
    {
        // verify user email address
        if (!$user = User::where('phone_number', $request->phone_number)->first()) {
            return response()->json([
                'status' => false,
                'message' => trans('auth.failed'),
            ], 404);
        }

        // check if phone number has been verified
        if ($user->phone_number_verified_at) {
            throw ValidationException::withMessages([
                'phone_number' => trans('auth.phone_verified')
            ]);
        }

        // send sms verification link to user
        $user->notify(new PhoneVerification($user->createToken('phone-verification', ['verify-phone-number'])->plainTextToken));

        return response()->json([
            'status' => true,
            'data' => [
                'phone_number' => $user->phone_number
            ],
            'message' => 'success',
        ]);
    }

    // forgot password
    public function forgotPassword(EmailVerificationRequest $request)
    {
        // verify user email address
        if (!$user = User::where('email_address', $request->email_address)->first()) {
            return response()->json([
                'status' => false,
                'message' => trans('auth.failed'),
            ], 404);
        }

        // send reset password link to user
        $user->notify(new ForgotPassword($user->createToken('forgot-password', ['reset-password'])->plainTextToken));

        return response()->json([
            'status' => true,
            'data' => [
                'email_address' => $user->email_address
            ],
            'message' => 'success',
        ]);
    }

    // reset password
    public function resetPassword(ResetPasswordRequest $request)
    {
        // reset user password
        $request->user()->update([
            'password' => Hash::make($request->password)
        ]);

        // delete reset password token
        $request->user()->currentAccessToken()->delete();

        // notify user
        $request->user()->notify(new ResetPassword());

        return response()->json([
            'status' => true,
            'data' => null,
            'message' => 'success',
        ]);
    }

    // logout
    public function logout(Request $request)
    {
        // delete login token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'data' => null,
            'message' => 'success',
        ]);
    }
}
