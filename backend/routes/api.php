<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Every route in this file is served under /api/v1 (see bootstrap/app.php).

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
});
