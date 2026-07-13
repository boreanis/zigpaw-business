<?php

use App\Http\Controllers\OAuth\PortalOAuthController;
use App\Livewire\PartnerDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', PartnerDashboard::class)->name('dashboard');
Route::get('/oauth/redirect', [PortalOAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('/oauth/callback', [PortalOAuthController::class, 'callback'])->name('oauth.callback');
Route::post('/oauth/logout', [PortalOAuthController::class, 'logout'])->name('oauth.logout');
