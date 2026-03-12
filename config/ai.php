<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI Features Flag
    |--------------------------------------------------------------------------
    |
    | When disabled, no AI API calls are made and the coach summary card is
    | not rendered. Safe on/off switch that requires no deploy.
    |
    */
    'enabled' => (bool) env('AI_FEATURES_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Anthropic Client
    |--------------------------------------------------------------------------
    */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'model' => env('AI_MODEL', 'claude-haiku-4-5'),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | TTL for coach summary responses. 7 days — cache hit rate expected to
    | be high since many podcasters share the same issues.
    |
    */
    'cache_ttl' => (int) env('AI_CACHE_TTL', 60 * 60 * 24 * 7),

];
