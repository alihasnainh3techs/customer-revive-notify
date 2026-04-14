<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TemplateController;

Route::middleware(['verify.shopify'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    })->name('home');

    Route::resource('create-campaign', CampaignController::class);

    Route::post('create-campaign/customer-count', [CampaignController::class, 'getFilteredCustomerCount'])
        ->name('create-campaign.customer-count');

    Route::resource('settings', SettingsController::class);

    Route::resource('templates', TemplateController::class);
});
