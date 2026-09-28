<?php

use App\Enums\Role;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
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

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/admin/dashboard', fn () => Inertia::render('Dashboard', [
        'area' => 'ADMIN',
    ]))->middleware('role:ADMIN')->name('admin.dashboard');

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
    });
});
