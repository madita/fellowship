<?php

namespace App\Http\Controllers\Admin;

// use App\Support\WebhookHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Announcement;
use App\Services\DiscordWebhookService;
use App\Support\DiscordEvents;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

class AnnouncementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     *
     *
     * @throws AuthorizationException
     *
     * @return Response
     */
    public function index(Request $request)
    {
        return null;
    }

    public function create()
    {
        // return View('admin.annoucement.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|max:255',
            'body'    => 'required|max:255',
            'thanks'  => 'required|max:255',
        ]);

        $message = $request->all();
        $users   = User::all();

        $announcement = new Announcement($message);
        Notification::send($users, $announcement);

        // The same announcement in the Discord channels that asked for it
        app(DiscordWebhookService::class)->announce(DiscordEvents::ANNOUNCEMENT_POSTED, [
            'title'       => $message['subject'],
            'description' => $message['body'],
            'url'         => $message['url'] ?? null,
            'author'      => $request->user()?->username,
        ]);

        return response()->json([
            'success'      => true,
            'announcement' => $announcement,
        ]);
    }
}
