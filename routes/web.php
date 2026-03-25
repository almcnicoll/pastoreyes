<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// Login page — shown to unauthenticated users
Route::get('/login', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('auth.login');
})->name('login');

// Disabled account page
Route::get('/account-disabled', function () {
    return view('auth.disabled');
})->name('account.disabled');

// Google OAuth
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->name('auth.google.redirect');

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('auth.google.callback');

Route::post('/logout', [GoogleAuthController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

/*
|--------------------------------------------------------------------------
| Authenticated Application Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // People
    Route::get('/people', function () {
        return view('people.index');
    })->name('people.index');

    Route::get('/people/{person}', function (\App\Models\Person $person) {
        return view('people.show', compact('person'));
    })->name('people.show');

    // Timeline
    Route::get('/timeline', function () {
        return view('timeline');
    })->name('timeline');

    // Settings
    Route::get('/settings', function () {
        return view('settings');
    })->name('settings');

});
