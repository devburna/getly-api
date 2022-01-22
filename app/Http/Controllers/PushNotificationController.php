<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePushNotificationRequest;
use App\Http\Requests\UpdatePushNotificationRequest;
use App\Models\PushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PushNotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StorePushNotificationRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StorePushNotificationRequest $request)
    {
        $request['user_id'] = $request->user()->id;

        PushNotification::create($request->only(['user_id', 'token']));

        return response()->json(['success' => true], 200);
    }

    /**
     * Send Push Notifications to all users.
     *
     * @return \Illuminate\Http\Response
     */
    public function send(Request $request)
    {
        if (!$request->has('to')) {
            foreach (PushNotification::get() as $push) {
                $response = $this->push($request->title, $request->body, $push->token);

                switch ($response->status()) {
                    case 200:
                        return $response->json();
                        break;

                    default:
                        return;
                        break;
                }
            }
        } else {
            $response = $this->push($request->title, $request->body, $request->to);
            switch ($response->status()) {
                case 200:
                    return $response->json();
                    break;

                default:
                    return;
                    break;
            }
        }
    }

    public function push($title, $body, $token, $action = true)
    {
        return Http::withHeaders([
            "Accept" => "application/json",
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . env("FCM_KEY")
        ])->post("https://fcm.googleapis.com/fcm/send", [
            "notification" => [
                "webpush" => [
                    "notification" => [
                        "title" => $title,
                        "body" => $body,
                        "requireInteraction" => $action,
                        "badge" => asset('img/logo.png'),
                    ]
                ]
            ],
            "to" => $token
        ]);
    }
}
