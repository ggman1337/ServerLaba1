<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NewController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/info/server', [NewController::class, 'serverInfo']);
Route::get('/info/client', [NewController::class, 'clientInfo']);
Route::get('/info/database', [NewController::class, 'databaseInfo']);