<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NewController;

Route::get('/', function () {
    return view('welcome');
});
Route::group(['prefix' => 'info'], function () {
    Route::get('/server', [NewController::class, 'serverInfo']);
    Route::get('/client', [NewController::class, 'clientInfo']);
    Route::get('/database', [NewController::class, 'databaseInfo']);
});