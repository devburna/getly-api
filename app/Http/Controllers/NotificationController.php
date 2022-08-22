<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $notifications = Notification::orderByDesc('created_at')->get();

        // sort user's notifications
        $userNotifications = [];

        foreach ($notifications as $notification) {
            if ($notification->data->user_id === $request->user()->id) {
                unset($notification->data->user_id);
                array_push($userNotifications, $notification->data);
            }
        }

        return response()->json([
            'status' => true,
            'data' => $userNotifications,
            'message' => 'success',
        ]);
    }
}
