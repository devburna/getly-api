<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignupRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Cloudinary\Api\Upload\UploadApi;

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

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateUserRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateUserRequest $request)
    {
        // secure password
        if ($request->has('password')) {
            $request['password'] = Hash::make($request->password);
        }

        // upload avatar
        if ($request->hasFile('avatar')) {
            $request['avatar_url'] = (new UploadApi())->upload($request->avatar->path(), [
                'folder' => config('app.name') . '/users/',
                'public_id' => $request->user()->id,
                'overwrite' => true,
                // 'notification_url' => '',
                'resource_type' => 'image'
            ])['secure_url'];
        }

        // update user details
        $request->user()->update($request->only([
            'username',
            'email_address',
            'phone_number',
            'avatar_url',
            'date_of_birth',
            'password',
        ]));

        return $this->index($request);
    }
}
