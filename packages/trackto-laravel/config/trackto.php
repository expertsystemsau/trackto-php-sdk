<?php

declare(strict_types=1);

use ExpertSystems\TrackTo\TrackToClient;

return [
    // Bearer API key. Required, checked when the client is first resolved, not at boot.
    'api_key' => env('TRACKTO_API_KEY'),

    // API origin. Blank means the production API.
    'base_url' => env('TRACKTO_BASE_URL') ?: TrackToClient::DEFAULT_BASE_URL,

    // Value sent as the Track-Version header. Blank means the version this SDK was built against.
    'version' => env('TRACKTO_API_VERSION') ?: TrackToClient::DEFAULT_VERSION,

    // Seconds to wait for the connection to open.
    'connect_timeout' => env('TRACKTO_CONNECT_TIMEOUT', 10),

    // Seconds to wait for the whole request.
    'timeout' => env('TRACKTO_TIMEOUT', 30),

    // Appended to the User-Agent header, for example "my-app/1.0". Blank means none.
    'user_agent_suffix' => env('TRACKTO_USER_AGENT_SUFFIX') ?: null,
];
