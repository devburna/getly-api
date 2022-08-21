<?php

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

Route::prefix('v1')->group(function () {

    # signup
    Route::post('signup', [\App\Http\Controllers\AuthController::class, 'register']);

    # signin
    Route::post('signin', [\App\Http\Controllers\AuthController::class, 'login']);

    # forgot-password
    Route::post('forgot-password', [\App\Http\Controllers\AuthController::class, 'forgotPassword']);

    # resend email verification link
    Route::get('verify-email-address', [\App\Http\Controllers\AuthController::class, 'resendEmailVerificationLink']);

    # resend phone verification link
    Route::get('verify-phone-number', [\App\Http\Controllers\AuthController::class, 'resendPhoneVerificationLink']);

    # authenticated
    Route::middleware(['auth:sanctum'])->group(function () {

        # reset-password
        Route::post('reset-password', [\App\Http\Controllers\AuthController::class, 'resetPassword'])->middleware(['ability:reset-password']);

        # email address verification
        Route::post('verify-email-address', [\App\Http\Controllers\AuthController::class, 'emailVerification'])->middleware(['ability:verify-email-address']);

        # phone number verification
        Route::post('verify-phone-number', [\App\Http\Controllers\AuthController::class, 'phoneVerification'])->middleware(['ability:verify-phone-number']);

        # logout
        Route::delete('logout', [\App\Http\Controllers\AuthController::class, 'logout'])->middleware(['ability:authenticate']);

        # user
        Route::prefix('user')->middleware(['ability:authenticate'])->group(function () {

            # details
            Route::get('', [\App\Http\Controllers\UserController::class, 'index']);

            # update details
            Route::patch('', [\App\Http\Controllers\UserController::class, 'update']);

            # update avatar
            Route::post('', [\App\Http\Controllers\UserController::class, 'update']);
        });
    });
});
