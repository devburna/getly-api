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

        #profile
        Route::prefix('profile')->group(function () {
            # me
            Route::get('', [\App\Http\Controllers\ProfileController::class, 'index']);

            # update profile
            Route::put('', [\App\Http\Controllers\ProfileController::class, 'update']);

            # update avatar
            Route::post('update-avatar', [\App\Http\Controllers\ProfileController::class, 'updateAvatar']);

            # update pin
            Route::post('update-pin', [\App\Http\Controllers\ProfileController::class, 'updatePin']);

            # update password
            Route::post('update-password', [\App\Http\Controllers\ProfileController::class, 'updatePassword']);
        });

        #getlists
        Route::prefix('getlists')->group(function () {
            # list
            Route::get('', [\App\Http\Controllers\GetlistController::class, 'index']);

            # create
            Route::post('', [\App\Http\Controllers\GetlistController::class, 'store']);

            #getlist
            Route::prefix('{getlist}')->group(function () {

                # getlist
                Route::get('', [\App\Http\Controllers\GetlistController::class, 'show']);

                # update
                Route::put('', [\App\Http\Controllers\GetlistController::class, 'update']);

                # update image
                Route::post('image', [\App\Http\Controllers\GetlistController::class, 'updateImage']);
            });
        });

        #gifts
        Route::prefix('gifts')->group(function () {

            # send gift
            Route::post('', [\App\Http\Controllers\GiftController::class, 'send']);

            # received
            Route::get('', [\App\Http\Controllers\GiftController::class, 'index']);

            # create
            Route::post('{getlist}', [\App\Http\Controllers\GiftController::class, 'create']);

            # fullfill
            Route::post('{gift}/fullfill', [\App\Http\Controllers\ContributorController::class, 'fullfill']);
        });

        #wallet
        Route::prefix('wallet')->group(function () {

            # show wallet
            Route::get('', [\App\Http\Controllers\WalletController::class, 'show']);
        });
    });
});
