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
    return view('welcome');
});

Route::prefix('verify')->group(function () {
    # send gift
    Route::post('sent-gift', [\App\Http\Controllers\GiftController::class, 'verifySentGift'])->name('verify-sent-gift');
});
