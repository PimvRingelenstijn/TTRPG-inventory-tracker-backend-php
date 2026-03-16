<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supabase Configuration
    |--------------------------------------------------------------------------
    |
    | Used for Supabase Auth (sign up, sign in, get user). Mirror of Python
    | config: SUPABASE_URL and SUPABASE_SERVICE_KEY from .env
    |
    */

    'url' => env('SUPABASE_URL'),
    'service_key' => env('SUPABASE_SERVICE_KEY'),

];
