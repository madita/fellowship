<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Yjs WebSocket Server
    |--------------------------------------------------------------------------
    |
    | Connection details for the Yjs collaboration WebSocket server.
    |
    */
    'yjs_host' => env('YJS_WS_HOST', '127.0.0.1'),
    'yjs_port' => env('YJS_WS_PORT', 1234),

    /*
    |--------------------------------------------------------------------------
    | Sandbox Feature Defaults
    |--------------------------------------------------------------------------
    |
    | Default values for sandbox settings (overridden by DB settings).
    |
    */
    'enabled' => env('SANDBOX_ENABLED', true),
    'public_enabled' => env('SANDBOX_PUBLIC_ENABLED', true),
    'collaboration_enabled' => env('SANDBOX_COLLABORATION_ENABLED', true),
    'autosave_interval' => env('SANDBOX_AUTOSAVE_INTERVAL', 30),

    /*
    |--------------------------------------------------------------------------
    | Default Role Limits
    |--------------------------------------------------------------------------
    |
    | Default limits per role. 0 = unlimited.
    | Overridden by the sandbox_role_limits DB setting.
    |
    */
    'default_role_limits' => [
        'admin' => [
            'max_sandboxes' => 0,
            'max_collaborators' => 0,
            'max_versions' => 0,
        ],
        'user' => [
            'max_sandboxes' => 0,
            'max_collaborators' => 0,
            'max_versions' => 0,
        ],
    ],
];
