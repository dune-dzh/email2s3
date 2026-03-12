<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')->get('/health', function () {
    return response()->json(['status' => 'ok']);
});

