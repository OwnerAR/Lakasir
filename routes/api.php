<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Api\Tenants\OmniChannel\MessageController as TenantOmniMessageController;

Route::group(['prefix' => 'domain'], function ()
{
    Route::post('/register', RegisteredUserController::class)
        ->name('register');
});

// Route::prefix('tenants/omni')->group(function () {
//     Route::post('/messages', [TenantOmniMessageController::class, 'receive']);
// });

Route::get('/test', function ()
{
    return response()->json([
        'message' => 'Success!',
    ]);
});

