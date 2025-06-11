<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AttendanceController;

Route::group(['prefix' => 'domain'], function ()
{
    Route::post('/register', RegisteredUserController::class)
        ->name('register');
});

Route::post('/attendance', [AttendanceController::class, 'storeAttendance'])
    ->middleware(['bot.api']);

Route::get('/test', function ()
{
    return response()->json([
        'message' => 'Success!',
    ]);
});

