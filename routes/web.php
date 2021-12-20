<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'status' => true,
        'message' => 'Serve is up and running.'
    ]);
});

Route::prefix('verify')->group(function () {
    # payment
    Route::get('payment', [\App\Http\Controllers\TransactionController::class, 'verify'])->name('verify-payment');

    # send gift
    Route::get('sent-gift', [\App\Http\Controllers\GiftController::class, 'verifySentGift'])->name('verify-sent-gift');

    # verify-wish
    Route::get('sent-wish', [\App\Http\Controllers\ContributorController::class, 'verifySentWish'])->name('verify-sent-wish');
});
