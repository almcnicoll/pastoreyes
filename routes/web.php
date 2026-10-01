<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Livewire\ContactSyncReviews;
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

Route::view('/terms', 'terms')->name('terms');
Route::view('/privacy', 'privacy')->name('privacy');

/*
|--------------------------------------------------------------------------
| Authenticated Application Routes
|--------------------------------------------------------------------------
*/


// Public index page for guests
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('index');
})->name('index');

Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/people', PeopleIndex::class)->name('people.index');

    Route::get('/people/{person}', PersonShow::class)->name('people.show');

    Route::get('/timeline', Timeline::class)->name('timeline');

    Route::get('/tasks', Tasks::class)->name('tasks');

    Route::get('/contact-sync', ContactSyncReviews::class)->name('contact-sync');

    Route::get('/settings', Settings::class)->name('settings');

});

/*
|--------------------------------------------------------------------------
| Local development login
|--------------------------------------------------------------------------
|
| Skips Google sign-in. Only registered when APP_ENV=local, and still refuses
| any host other than localhost. Logs in as the first administrator, or as
| ?user=<id>. Never present in production/testing environments.
|
*/
if (app()->environment('local')) {
    Route::get('/dev-login', function (\Illuminate\Http\Request $request) {
        abort_unless(in_array($request->getHost(), ['localhost', '127.0.0.1']), 404);

        $user = $request->query('user')
            ? \App\Models\User::findOrFail($request->query('user'))
            : \App\Models\User::where('is_admin', true)->firstOrFail();

        \Illuminate\Support\Facades\Auth::login($user);

        return redirect()->route('dashboard');
    })->name('dev-login');
}
