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

        # getlists
        Route::prefix('getlists')->middleware(['ability:authenticate'])->group(function () {

            # create
            Route::post('', [\App\Http\Controllers\GetlistController::class, 'store']);

            # all
            Route::get('', [\App\Http\Controllers\GetlistController::class, 'index']);

            Route::prefix('{getlist}')->group(function () {

                # details
                Route::get('', [\App\Http\Controllers\GetlistController::class, 'show'])->can('view', 'getlist');

                # update details
                Route::post('', [\App\Http\Controllers\GetlistController::class, 'update'])->can('update', 'getlist');

                # toggle
                Route::delete('', [\App\Http\Controllers\GetlistController::class, 'destroy'])->withTrashed()->can('delete', 'getlist')->can('restore', 'getlist')->can('forceDelete', 'getlist');
            });
        });

        # gifts
        Route::prefix('gifts')->middleware(['ability:authenticate'])->group(function () {

            # create
            Route::post('', [\App\Http\Controllers\GetlistItemController::class, 'store']);

            # all
            Route::get('', [\App\Http\Controllers\GetlistItemController::class, 'index']);

            Route::prefix('{getlistItem}')->group(function () {

                # contribute
                Route::patch('', [\App\Http\Controllers\GetlistItemController::class, 'contribute']);

                # details
                Route::get('', [\App\Http\Controllers\GetlistItemController::class, 'show'])->can('view', 'getlistItem');

                # update details
                Route::post('', [\App\Http\Controllers\GetlistItemController::class, 'update'])->can('update', 'getlistItem');

                # toggle
                Route::delete('', [\App\Http\Controllers\GetlistItemController::class, 'destroy'])->withTrashed()->can('delete', 'getlistItem')->can('restore', 'getlistItem')->can('forceDelete', 'getlistItem');
            });
        });

        # gift cards
        Route::prefix('gift-cards')->middleware(['ability:authenticate'])->group(function () {

            # create
            Route::post('', [\App\Http\Controllers\GiftCardController::class, 'create']);

            # all
            Route::get('', [\App\Http\Controllers\GiftCardController::class, 'index']);

            Route::prefix('{giftCard}')->group(function () {

                # details
                Route::get('', [\App\Http\Controllers\GiftCardController::class, 'show']);

                # update details
                Route::post('', [\App\Http\Controllers\GiftCardController::class, 'update']);

                # toggle
                Route::delete('', [\App\Http\Controllers\GiftCardController::class, 'destroy']);
            });
        });

        # redeem gift
        Route::prefix('redeem-gift')->middleware(['ability:redeem-gift-card'])->group(function () {

            # preview
            Route::get('', [\App\Http\Controllers\GiftCardController::class, 'preview']);

            # redeem
            Route::post('', [\App\Http\Controllers\GiftCardController::class, 'redeem']);
        });

        # wallet
        Route::prefix('wallet')->middleware(['ability:authenticate'])->group(function () {

            # details
            Route::get('', [\App\Http\Controllers\WalletController::class, 'show']);

            # withdraw
            Route::post('withdraw', [\App\Http\Controllers\WalletController::class, 'withdraw']);
        });

        # virtual card
        Route::prefix('virtual-card')->middleware(['ability:authenticate'])->group(function () {

            # create
            Route::post('', [\App\Http\Controllers\VirtualCardController::class, 'create']);

            # details
            Route::get('', [\App\Http\Controllers\VirtualCardController::class, 'show']);

            # fund
            Route::post('fund', [\App\Http\Controllers\VirtualCardController::class, 'fund']);

            # withdraw
            Route::post('withdraw', [\App\Http\Controllers\VirtualCardController::class, 'withdraw']);

            # transactions
            Route::get('transactions', [\App\Http\Controllers\VirtualCardController::class, 'transactions']);
        });

        # virtual account
        Route::prefix('virtual-account')->middleware(['ability:authenticate'])->group(function () {

            # create
            Route::post('', [\App\Http\Controllers\VirtualAccountController::class, 'create']);

            # details
            Route::get('', [\App\Http\Controllers\VirtualAccountController::class, 'show']);
        });

        # kyc
        Route::prefix('kyc')->middleware(['ability:authenticate'])->group(function () {

            # bvn
            Route::get('bvn', [\App\Http\Controllers\KYCController::class, 'bvn']);
        });
    });
});
