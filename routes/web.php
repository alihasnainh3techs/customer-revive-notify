<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\SmtpConfigurationController;

Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    })->name('home');

    Route::get('/explore', [ExploreController::class, 'index'])->name('explore');

    Route::resource('campaigns', CampaignController::class);

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::resource('smtp', SmtpConfigurationController::class)
            ->parameters(['smtp' => 'smtpConfiguration']);
    });

    Route::view('/settings/whatsapp', 'settings.whatsapp')->name('settings.whatsapp');

    Route::resource('templates', TemplateController::class);
});
