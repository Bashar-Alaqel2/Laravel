<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function() {
    return response()->json(['status' => 'alive', 'message' => 'Render backend is awake!']);
});

Route::get('/clear-cache', function() {
    Artisan::call('optimize:clear');
    Artisan::call('migrate', ['--force' => true]);
    return 'Cache cleared and Database migrated successfully!';
});
