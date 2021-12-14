<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Profile;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use League\CommonMark\Normalizer\SlugNormalizer;

class ProfileController extends Controller
{

    private $cloudinary;

    public function __construct()
    {
        $this->cloudinary = (new UploadApi());
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $user->profile;

        return response()->json([
            'status' => true,
            'data' => $user,
            'message' => 'Fetched'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        Profile::create([
            'user_id' => $request->user_id,
            'phone' => $request->phone_code . $request->phone,
            'birthday' => $request->birthday,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updatePin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_pin' => ['required', function ($attribute, $value, $fail) use ($request) {
                if (!Hash::check($value, $request->user()->profile->password)) {
                    return $fail(__('The current pin is incorrect.'));
                }
            }],
            'pin' => 'required|digits:4',
            'pin_confirmation' => 'same:pin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $request->user()->profile->update([
            'password' => Hash::make($request->pin_confirmation),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Success',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', function ($attribute, $value, $fail) use ($request) {
                if (!Hash::check($value, $request->user()->password)) {
                    return $fail(__('The current password is incorrect.'));
                }
            }],
            'password' => 'required|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $request->user()->profile->update([
            'password' => Hash::make($request->pin_confirmation),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Success',
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Profile  $profile
     * @return \Illuminate\Http\Response
     */
    public function updateAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|mimes:jpg,jpeg,png|max:3000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $request->user()->profile->update([
            'avatar' => $this->cloudinary->upload($request->avatar->path(), [
                'folder' => 'getly/users/',
                'public_id' => (new SlugNormalizer())->normalize(strtolower($request->user()->name)),
                'overwrite' => true,
                // 'notification_url' => '',
                'resource_type' => 'image'
            ])['secure_url'],
        ]);

        return response()->json([
            'status' => true,
            'data' => $request->user(),
            'message' => 'Success',
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Profile  $profile
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateProfileRequest $request)
    {
        if ($request->has('name')) {
            $request->user()->update($request->only(['name']));
        }
        
        $request->user()->profile->update($request->only(['birthday', 'phone', 'phone_verified_at']));

        return response()->json([
            'status' => true,
            'data' => $request->user(),
            'message' => 'Success',
        ]);
    }
}
