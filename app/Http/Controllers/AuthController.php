<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
        return response()->json([
            'status' => true,
            'message' => trans('auth.email-verified'),
        ]);
    }

    // set-pin
    public function setPin(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'Success',
        ]);
    }

    // verify-pin
    public function verifyPin(Request $request)
    {
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
