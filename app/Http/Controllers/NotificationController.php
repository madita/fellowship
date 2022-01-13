<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class NotificationController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $user = User::find($userId);
        $notifications = $user->notifications;

//        return view('account.notification.index', compact('notifications'));
    }

    public function notification(){
        $notifications = auth()->user()->unreadNotifications;
        return $notifications;
    }

    public function notificationread(){
        auth()->user()->unreadNotifications->markAsRead();
        return ['message' => 'All notifications are mark as read.'];
    }

    public function notificationdelete(Request $request){
        auth()->user()->notifications($request->id)->delete();
        return ['message' => 'Notifications was deleted successfully.'];
    }

    public function notificationsingleread(Request $request){
        auth()->user()->unreadNotifications($request->id)->update(['read_at' => now()]);;
        return ['message' => 'notifications was marked as read.'];
    }
}
