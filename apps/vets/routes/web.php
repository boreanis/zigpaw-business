<?php

use App\Http\Controllers\Clinical\ProviderMediaController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OAuth\PortalOAuthController;
use App\Http\Middleware\RequirePortalWorkspace;
use App\Livewire\Clinical\CareSubmission;
use App\Livewire\Clinical\PatientIndex;
use App\Livewire\Clinical\PatientShow;
use App\Livewire\Clinical\SubmissionIndex;
use App\Livewire\Clinical\SubmissionShow;
use App\Livewire\VetDashboard;
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

Route::get('/', VetDashboard::class)->name('dashboard');

Route::middleware(RequirePortalWorkspace::class)->group(function (): void {
    Route::get('/patients', PatientIndex::class)->name('patients.index');
    Route::get('/patients/{grantId}', PatientShow::class)->whereUuid('grantId')->name('patients.show');
    Route::get('/patients/{grantId}/care-submissions/new', CareSubmission::class)
        ->whereUuid('grantId')
        ->name('patients.submissions.create');
    Route::get('/patients/{grantId}/media/{mediaId}', ProviderMediaController::class)
        ->whereUuid('grantId')
        ->whereNumber('mediaId')
        ->name('patients.media.show');
    Route::get('/submissions', SubmissionIndex::class)->name('submissions.index');
    Route::get('/submissions/{submissionId}', SubmissionShow::class)
        ->whereUuid('submissionId')
        ->name('submissions.show');
});

Route::get('/auth/login', [PortalOAuthController::class, 'redirect'])->name('auth.login');
Route::get('/auth/callback', [PortalOAuthController::class, 'callback'])->name('auth.callback');
Route::post('/auth/logout', [PortalOAuthController::class, 'logout'])->name('auth.logout');
