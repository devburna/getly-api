<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    // register
    public function register(Request $request)
    {
        $request['message'] = trans('auth.signup');
        $request['code'] = 201;
        return $this->login($request);
    }

    // login
    public function login(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => [
                'token' => null
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
        return response()->json([
            'status' => true,
            'message' => 'Success',
        ]);
    }
}
