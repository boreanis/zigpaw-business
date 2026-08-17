<?php

use App\Http\Controllers\Clinical\ProviderMediaController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OAuth\ClinicalPortalOAuthController;
use App\Http\Controllers\OAuth\PortalOAuthController;
use App\Http\Middleware\RequireClinicalWorkspace;
use App\Livewire\BusinessWorkspace;
use App\Livewire\Clinical\CareSubmission;
use App\Livewire\Clinical\PatientIndex;
use App\Livewire\Clinical\PatientShow;
use App\Livewire\Clinical\SubmissionIndex;
use App\Livewire\Clinical\SubmissionShow;
use App\Livewire\ClinicalDashboard;
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

Route::get('/clinical', ClinicalDashboard::class)->name('clinical.dashboard');
Route::get('/clinical/auth/login', [ClinicalPortalOAuthController::class, 'redirect'])->name('clinical.auth.login');
Route::get('/clinical/auth/callback', [ClinicalPortalOAuthController::class, 'callback'])->name('clinical.auth.callback');
Route::post('/clinical/auth/logout', [ClinicalPortalOAuthController::class, 'logout'])->name('clinical.auth.logout');

Route::middleware(RequireClinicalWorkspace::class)->group(function (): void {
    Route::get('/clinical/patients', PatientIndex::class)->name('clinical.patients.index');
    Route::get('/clinical/patients/{grantId}', PatientShow::class)->whereUuid('grantId')->name('clinical.patients.show');
    Route::get('/clinical/patients/{grantId}/care-submissions/new', CareSubmission::class)
        ->whereUuid('grantId')
        ->name('clinical.patients.submissions.create');
    Route::get('/clinical/patients/{grantId}/media/{mediaId}', ProviderMediaController::class)
        ->whereUuid('grantId')
        ->whereNumber('mediaId')
        ->name('clinical.patients.media.show');
    Route::get('/clinical/submissions', SubmissionIndex::class)->name('clinical.submissions.index');
    Route::get('/clinical/submissions/{submissionId}', SubmissionShow::class)
        ->whereUuid('submissionId')
        ->name('clinical.submissions.show');
});
