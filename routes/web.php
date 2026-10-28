<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MysteryBoxController;
use App\Http\Controllers\SmsController;
use Illuminate\Support\Facades\Log;

Route::get('/', function () {
    // return view('welcome');
    return response()->json(['message' => 'Mystery Box API']);
});


Route::get('/login', function () {
    return view('welcome');
});

Route::get('/test-log', function () {
    Log::info('Test log entry from /test-log endpoint');
    return response()->json(['message' => 'Log entry created']);
});

Route::get('/test-mystery-box-log', function () {
    Log::info('Test mystery box log entry from /test-mystery-box-log endpoint');
    return response()->json(['message' => 'Mystery box log entry created']);
});

Route::get('/docs/mystery-box', function () {
    return view('downloads.mystery_box_docs');
})->name('mystery.box.docs');
