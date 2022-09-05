<?php

header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept,charset,boundary,Content-Length');
header('Access-Control-Allow-Origin: *');

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

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

    #status
    Route::get('/', function (Request $request) {
        return response()->json([
            'status' => true,
            'data' => $request->server(),
            'message' => 'Server is up and running.'
        ]);
    });

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
        Route::prefix('user')->middleware(['ability:authenticate', 'is_email_verified'])->group(function () {

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
                Route::get('', [\App\Http\Controllers\GetlistController::class, 'show'])->can('view', 'getlist')->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });

                # update details
                Route::post('', [\App\Http\Controllers\GetlistController::class, 'update'])->can('update', 'getlist')->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });

                # toggle
                Route::delete('', [\App\Http\Controllers\GetlistController::class, 'destroy'])->withTrashed()->can('delete', 'getlist')->can('restore', 'getlist')->can('forceDelete', 'getlist')->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });
            });
        });

        # gifts
        Route::prefix('gifts')->middleware(['ability:authenticate'])->group(function () {

            # create
            Route::post('', [\App\Http\Controllers\GetlistItemController::class, 'store']);

            # all
            Route::get('', [\App\Http\Controllers\GetlistItemController::class, 'index']);

            Route::prefix('{getlistItem}')->group(function () {

                # details
                Route::get('', [\App\Http\Controllers\GetlistItemController::class, 'show'])->can('view', 'getlistItem')->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });

                # update details
                Route::post('', [\App\Http\Controllers\GetlistItemController::class, 'update'])->can('update', 'getlistItem')->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });

                # toggle
                Route::delete('', [\App\Http\Controllers\GetlistItemController::class, 'destroy'])->withTrashed()->can('delete', 'getlistItem')->can('restore', 'getlistItem')->can('forceDelete', 'getlistItem')->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });
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
                Route::get('', [\App\Http\Controllers\GiftCardController::class, 'show'])->can('update', 'giftCard')->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });

                # redeem
                Route::post('redeem', [\App\Http\Controllers\GiftCardController::class, 'redeem'])->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });

                # update details
                Route::post('', [\App\Http\Controllers\GiftCardController::class, 'update'])->can('update', 'giftCard')->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });

                # toggle
                Route::delete('', [\App\Http\Controllers\GiftCardController::class, 'destroy'])->can('update', 'giftCard')->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });
            });
        });

        # wallet
        Route::prefix('wallet')->middleware(['ability:authenticate'])->group(function () {

            # details
            Route::get('', [\App\Http\Controllers\WalletController::class, 'show']);

            # fund
            Route::put('', [\App\Http\Controllers\WalletController::class, 'fund']);

            # verify payment
            Route::patch('', [\App\Http\Controllers\WalletController::class, 'webHook']);

            # withdraw
            Route::post('', [\App\Http\Controllers\WalletController::class, 'transfer']);
        });

        # virtual card
        Route::prefix('virtual-card')->middleware(['ability:authenticate'])->group(function () {

            # create
            Route::post('', [\App\Http\Controllers\VirtualCardController::class, 'create']);

            # details
            Route::get('', [\App\Http\Controllers\VirtualCardController::class, 'show']);

            # fund
            Route::put('', [\App\Http\Controllers\VirtualCardController::class, 'fund']);

            # transactions
            Route::get('transactions', [\App\Http\Controllers\VirtualCardController::class, 'transactions']);

            # toggle
            Route::delete('', [\App\Http\Controllers\VirtualCardController::class, 'toggle']);
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

            # bank details
            Route::get('bank', [\App\Http\Controllers\KYCController::class, 'bank']);
        });

        # transactions
        Route::prefix('transactions')->middleware(['ability:authenticate'])->group(function () {

            # all
            Route::get('', [\App\Http\Controllers\TransactionController::class, 'index']);

            Route::prefix('{transaction}')->group(function () {

                # details
                Route::get('', [\App\Http\Controllers\TransactionController::class, 'show'])->can('view', 'transaction')->missing(function () {
                    throw ValidationException::withMessages([
                        'message' => "Resource has been removed."
                    ]);
                });
            });
        });

        # notifications
        Route::get('notifications', [\App\Http\Controllers\NotificationController::class, 'index']);

        # webhooks
        Route::get('webhooks', [\App\Http\Controllers\WebhookController::class, 'index']);

        # banks
        Route::get('banks', [\App\Http\Controllers\KYCController::class, 'banks'])->middleware(['ability:authenticate']);
    });

    # contribute
    Route::prefix('contribute/{getlistItem}')->group(function () {

        # get link
        Route::post('', [\App\Http\Controllers\GetlistItemController::class, 'contribute'])->missing(function () {
            throw ValidationException::withMessages([
                'message' => "Resource has been removed."
            ]);
        });

        # verify payment
        Route::get('', [\App\Http\Controllers\GetlistItemController::class, 'webHook'])->missing(function () {
            throw ValidationException::withMessages([
                'message' => "Resource has been removed."
            ]);
        });
    });
});

# webhooks

# flutterwave
Route::post('flutterwave', [\App\Http\Controllers\TransactionController::class, 'create']);

# mono
Route::post('mono', [\App\Http\Controllers\TransactionController::class, 'create']);
