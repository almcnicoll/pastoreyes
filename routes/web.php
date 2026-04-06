<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Livewire\Dashboard;
use App\Livewire\People\PeopleIndex;
use App\Livewire\People\PersonShow;
use App\Livewire\Tasks;
use App\Livewire\Timeline;
use App\Livewire\Settings;
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
    Route::get('/', fn() => redirect()->route('dashboard'));

    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/people', PeopleIndex::class)->name('people.index');

    Route::get('/people/{person}', PersonShow::class)->name('people.show');

    Route::get('/timeline', Timeline::class)->name('timeline');

    Route::get('/tasks', Tasks::class)->name('tasks');

    Route::get('/settings', Settings::class)->name('settings');

});
