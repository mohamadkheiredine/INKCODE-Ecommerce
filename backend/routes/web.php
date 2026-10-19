<?php

use Illuminate\Support\Facades\Route;

// API-only backend: the React SPA is built and deployed separately.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
    'docs' => url('/docs/api'),
]));
