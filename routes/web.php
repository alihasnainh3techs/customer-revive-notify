<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BrandedSMSController;
use App\Http\Controllers\TexnityController;
use App\Http\Controllers\WhatomationController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\SmtpConfigurationController;
use App\Http\Controllers\DeviceController;

Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');

    Route::get('/explore', [ExploreController::class, 'index'])->name('explore');

    Route::get('campaigns/{campaign}/logs', [CampaignController::class, 'logs'])->name('campaigns.logs');

    Route::resource('campaigns', CampaignController::class);

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

    Route::prefix('settings')->name('settings.')->group(function () {

        Route::resource('bsp', BrandedSMSController::class);

        Route::resource('whatomation', WhatomationController::class);

        Route::resource('texnity', TexnityController::class);

        Route::resource('smtp', SmtpConfigurationController::class)
            ->parameters(['smtp' => 'smtpConfiguration']);

        Route::resource('whatsapp', DeviceController::class);

        Route::get('whatsapp/status', [DeviceController::class, 'status'])->name('whatsapp.status');

        Route::patch('whatsapp/{device}/toggle-notifications', [DeviceController::class, 'toggleWhatsAppNotifications'])->name('whatsapp.toggle-notifications');
    });

    Route::resource('templates', TemplateController::class);
});
