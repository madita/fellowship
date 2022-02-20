<?php

namespace App\Http\Controllers\Admin;

//use App\Helpers\WebhookHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Announcement;
use Illuminate\Http\Request;
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
     * @param Request $request
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

    }

    public function create()
    {
        //return View('admin.annoucement.create');
    }

    public function store(Request $request)
    {

        $request->validate([
            'subject' => 'required|max:255',
            'body'    => 'required|max:255',
            'thanks'  => 'required|max:255',
        ]);

        $message = $request->all();
        $users = User::all();

        $announcement = new Announcement($message);
        Notification::send($users, $announcement);

        return response()->json([
            'success' => true,
            'announcement' => $announcement,
        ]);

    }


}
