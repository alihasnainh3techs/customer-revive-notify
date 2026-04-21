<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\SmtpConfigurationController;
use App\Http\Controllers\DeviceController;

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

        Route::resource('whatsapp', DeviceController::class);
        // Add custom status route
        Route::get('whatsapp/status', [DeviceController::class, 'status'])->name('whatsapp.status');

        Route::patch('whatsapp/{device}/toggle-notifications', [DeviceController::class, 'toggleWhatsAppNotifications'])->name('whatsapp.toggle-notifications');
    });

    Route::resource('templates', TemplateController::class);
});
