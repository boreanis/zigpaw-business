<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\OAuth\PortalOAuthController;
use App\Livewire\BusinessWorkspace;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'live'])
        ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
        ->name('health');
    Route::get('/health/ready', [HealthController::class, 'ready'])
        ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
        ->name('health.ready');
});

Route::get('/', BusinessWorkspace::class)->name('dashboard');
Route::get('/auth/login', [PortalOAuthController::class, 'redirect'])->name('auth.login');
Route::get('/auth/callback', [PortalOAuthController::class, 'callback'])->name('auth.callback');
Route::post('/auth/logout', [PortalOAuthController::class, 'logout'])->name('auth.logout');
