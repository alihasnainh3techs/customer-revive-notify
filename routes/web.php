<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TemplateController;

Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    })->name('home');

    Route::get('/create-campaign', function () {
        return view('create-campaign');
    })->name('create-campaign');

    Route::get('/settings', function () {
        $templates = Auth::user()
            ->templates()
            ->orderBy('created_at', 'desc')
            ->get();

        return view('settings', compact('templates'));
    })->name('settings');

    Route::resource('templates', TemplateController::class);
});
