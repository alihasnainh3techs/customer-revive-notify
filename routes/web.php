<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    })->name('home');

    Route::get('/create-campaign', function () {
        return view('create-campaign');
    })->name('create-campaign');

    Route::get('/settings', function () {
        return view('settings');
    })->name('settings');
});
