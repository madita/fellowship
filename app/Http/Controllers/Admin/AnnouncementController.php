<?php

namespace App\Http\Controllers\Admin;

//use App\Helpers\WebhookHelper;
use GuzzleHttp\Client as HttpClient;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Http\Controllers\Controller;


use App\Notifications\Announcement;

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
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Request $request)
    {
        return;
    }

    public function create(){
        //return View('admin.annoucement.create');
    }

    public function store(Request $request){

        $request->validate([
            'subject' => 'required|max:255',
            'body' => 'required|max:255',
            'thanks' => 'required|max:255',
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
