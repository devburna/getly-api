<?php

namespace App\Http\Controllers;

use App\Events\PushNotification;
use App\Models\User;
use App\Notifications\PushDemo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class PushController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store the PushSubscription.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'endpoint'    => 'required',
            'keys.auth'   => 'required',
            'keys.p256dh' => 'required'
        ]);
        $endpoint = $request->endpoint;
        $token = $request->keys['auth'];
        $key = $request->keys['p256dh'];
        $user = $request->user();
        $user->updatePushSubscription($endpoint, $key, $token);

        return response()->json(['success' => true], 200);
    }

    /**
     * Send Push Notifications to all users.
     *
     * @return \Illuminate\Http\Response
     */
    public function test()
    {
        event(new PushNotification([
            'subject' => 'Push Notification',
            'body' => 'Push Notification Works 😎',
            'icon' => asset('img/logo.png')
        ]));

        return "ok";
    }
}
