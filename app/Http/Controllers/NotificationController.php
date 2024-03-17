<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $user = User::find($userId);
        $notifications = $user->notifications;

        return response($notifications);
    }

    public function notification()
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->unreadNotifications;
    }

    public function notificationread()
    {
        /** @var User $user */
        $user = auth()->user();
        $user->unreadNotifications->markAsRead();

        return ['message' => 'All notifications are mark as read.'];
    }

    public function notificationdelete(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();
        $notification = $user->notifications()->find($request->id);
        if (!$notification) {
            return ['message' => 'notifications does not exist.'];
        }

        $notification->delete();

        return ['message' => 'Notification was deleted successfully.'];
    }

    public function notificationsingleread(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();
        $notification = $user->notifications()->find($request->id);

        if (!$notification) {
            return ['message' => 'notifications does not exist.'];
        }
        $notification->markAsRead();

        return ['message' => 'notifications was marked as read.'];
    }
}
