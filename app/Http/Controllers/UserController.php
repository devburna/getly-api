<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // add wallet to data
        $request->user()->wallet;

        return response()->json([
            'status' => true,
            'data' => $request->user(),
            'message' => 'success',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\SignupRequest  $request
     */
    public function store(SignupRequest $request)
    {
        return User::create($request->only([
            'first_name',
            'last_name',
            'username',
            'email_address',
            'phone_number',
            'date_of_birth',
            'password',
        ]));
    }
}
