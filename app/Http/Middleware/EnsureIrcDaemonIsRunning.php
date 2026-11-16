<?php

namespace App\Http\Middleware;

use App\Services\Irc\IrcConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everything the IRC client does goes through the daemon: it is the only
 * thing that holds the socket to the IRC server and consumes the command
 * queue. With the daemon down a command is pushed into Redis and sits there,
 * so the request looks like it worked while nothing happens — refuse it
 * instead and let the client say the chat is unavailable.
 */
class EnsureIrcDaemonIsRunning
{
    public function handle(Request $request, Closure $next): Response
    {
        if ( ! IrcConnectionManager::isDaemonRunning()) {
            return response()->json([
                'message'        => __('messages.irc.daemon_offline'),
                'daemon_running' => false,
            ], 503);
        }

        return $next($request);
    }
}
