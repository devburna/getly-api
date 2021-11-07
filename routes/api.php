<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::prefix('v1')->group(function () {

    # signup
    Route::post('signup', [\App\Http\Controllers\AuthController::class, 'register']);

    # signin
    Route::post('signin', [\App\Http\Controllers\AuthController::class, 'login']);

    # verify-email
    Route::post('verify-email', [\App\Http\Controllers\AuthController::class, 'verifyEmail']);

    # forgot-password
    Route::post('forgot-password', [\App\Http\Controllers\AuthController::class, 'recover']);

    # reset-password
    Route::post('reset-password', [\App\Http\Controllers\AuthController::class, 'reset']);

    #authenticated
    Route::middleware(['auth:sanctum', 'email_verified'])->group(function () {

        # set-pin
        Route::post('set-pin', [\App\Http\Controllers\AuthController::class, 'setPin']);

        # verify-pin
        Route::post('verify-pin', [\App\Http\Controllers\AuthController::class, 'verifyPin']);

        # logout
        Route::post('logout', [\App\Http\Controllers\AuthController::class, 'logout']);
    });
});
