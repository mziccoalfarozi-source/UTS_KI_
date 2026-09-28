<?php

use App\Enums\Role;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

    Route::get('/signer/dashboard', fn () => Inertia::render('Dashboard', [
        'area' => 'SIGNER',
    ]))->middleware('role:SIGNER')->name('signer.dashboard');
});
