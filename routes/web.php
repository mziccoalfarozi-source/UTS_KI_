<?php

use App\Enums\Role;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentSigningController;
use App\Http\Controllers\SigningKeyController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', function (Request $request): RedirectResponse {
    if ($request->user()?->role === Role::Admin) {
        return redirect()->route('admin.dashboard');
    }

    if ($request->user()?->role === Role::Signer) {
        return redirect()->route('signer.dashboard');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::get('/verify/{token}', fn (string $token): Response => Inertia::render('Verify/Placeholder', [
    'token' => $token,
]))->name('verify.show');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download');

    Route::middleware('role:ADMIN')->group(function (): void {
        Route::get('/admin/dashboard', fn () => Inertia::render('Dashboard', [
            'area' => 'ADMIN',
        ]))->name('admin.dashboard');

        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    });

    Route::middleware('role:SIGNER')->group(function (): void {
        Route::get('/signer/dashboard', function (Request $request): Response {
            $signingKey = $request->user()->signingKey()->first(['created_at']);

            return Inertia::render('Dashboard', [
                'area' => 'SIGNER',
                'signingKey' => $signingKey === null ? null : [
                    'created_at' => $signingKey->created_at->toIso8601String(),
                ],
            ]);
        })->name('signer.dashboard');

        Route::get('/keys', [SigningKeyController::class, 'index'])->name('keys.index');
        Route::post('/keys', [SigningKeyController::class, 'store'])->name('keys.store');
        Route::post('/documents/{document}/sign', [DocumentSigningController::class, 'store'])
            ->name('documents.sign');
    });
});
