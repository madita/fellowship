<?php

namespace App\Http\Controllers\Conversation;

use App\Events\ConversationCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Models\Conversation\Conversation;
use App\Models\Conversation\ConversationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    public function index(Request $request): JsonResponse
    {


        $conversations = auth()->user()
            ->conversations()
            ->get();

       // dd($conversations);



       return response()->json([
            'data' => $conversations->map(function ($conversation) {
                return $this->transformConversation($conversation, ['users']);
            })
        ]);
    }

    public function show(Conversation $conversation, Request $request)
    {

       // dd('hmmm', $conversation);
        //ToDo authorize Contract
        //$this->authorize('show', $conversation);

        /*if ($conversation->isReply()) {
            abort(404);
        }*/

        $conversation->load([ 'users', 'messages', 'messages.user']);
        $conversation->loadCount('messages');

        $conversation->messages->each(function ($message) {
            $message->self_owned = $message->user_id === auth()->id();
            $message->created_at_human = $message->created_at->diffForHumans();
        });


        return response()->json([
            'data' => $conversation
        ]);
    }



    public function store(StoreConversationRequest $request): JsonResponse
    {
        //dd($request);
        //dd($request->get(  'recipients'));
        //$recipientsIds = collect($request->get(  'recipients'))->merge([auth()->user()])->pluck('id')->unique();
        $recipientsIds = collect($request->get(  'recipients'))->merge([auth()->user()->id])->unique();

       // dd($recipientsIds);

        $conversation = new Conversation;
        //$conversation->body = $request->get('body');
        //$conversation->user()->associate($request->user());
        $conversation->uuid = Str::uuid();
        $conversation->last_message_at = now();
        $conversation->save();

        $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->get('body'),
        ]);


        $conversation->users()->sync($recipientsIds);

        //$conversation->touchLastMessageAt();

        /*$conversation->users()->sync(array_unique(
            array_merge($request->get(  'recipients'), [$request->user()->id])
        ));*/

       // $conversation->load(['user', 'users', 'replies', 'replies.user']);
       // $conversation->loadCount('replies');

        //broadcast(new ConversationCreated($conversation))->toOthers();


        return response()->json([
            //'data' => $this->transformConversation($conversation, [ 'users', 'replies', 'replies.user'])
            'data' => $conversation
        ], 201);
    }

    /**
     * Transform conversation data for API response
     */
    private function transformConversation(Conversation $conversation, array $includes = []): array
    {
        $data = [
            'id' => $conversation->id,
            'uuid' => $conversation->uuid,
            //'parent_id' => $conversation->parent ? $conversation->parent->id : null,
            'body' => $conversation->messages->first()->body,
            'messages_count' => count($conversation->messages),
            'created_at_human' => $conversation->created_at->diffForHumans(),
            //'last_message_at_human' => $conversation->last_message_at ? $conversation->last_message_at->diffForHumans(): $conversation->created_at->diffForHumans(),
            'users' => $conversation->users,
            //'last_reply_human' => $conversation->last_reply ? $conversation->last_reply->diffForHumans() : null,
            //'participant_count' => $conversation->usersExceptCurrentlyAuthenticated->count(),
            'participant_count' => count($conversation->users) - 1,
            //'replies_count' => $conversation->replies()->count(),
            //'self_owned' => $conversation->getSelfOwnedAttribute(),
        ];

        // Include user data if requested
        /*if (in_array('user', $includes) && $conversation->relationLoaded('user')) {
            $data['user'] = $this->transformUser($conversation->user);
        }*/

        // Include users data if requested
        /*if (in_array('users', $includes) && $conversation->relationLoaded('users')) {
            $data['users'] = $conversation->users->map(function ($user) {
                return $this->transformUser($user);
            });
        }*/

        // Include parent data if requested
        /*if (in_array('parent', $includes) && $conversation->relationLoaded('parent') && $conversation->parent) {
            $data['parent'] = $this->transformConversation($conversation->parent, []);
        }*/

        // Include replies data if requested
       /* if (in_array('replies', $includes) && $conversation->relationLoaded('replies')) {
            $data['replies'] = $conversation->replies->map(function ($reply) use ($includes) {
                // Transform each reply as a conversation (since replies are also Conversation models)
                $replyIncludes = [];
                if (in_array('replies.user', $includes)) {
                    $replyIncludes[] = 'user';
                }

                return $this->transformConversation($reply, $replyIncludes);
            });
        }*/

        return $data;
    }

    /**
     * Transform user data for API response
     */
    private function transformUser($user): array
    {

        if (!$user) {
            return [];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            // Add other user fields as needed, but be careful with sensitive data
        ];
    }
}
