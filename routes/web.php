<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\GroupSendIndex;
use App\Livewire\ListContatosIndex;
use App\Livewire\PerfilIndex;
use App\Livewire\SendIndex;
use App\Livewire\StatisticIndex;
use App\Livewire\UsersIndex;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Statistic
Route::get('/statistic', StatisticIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('statistic.index');

// Dashboard
Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Users
Route::get('/users', UsersIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('users.index');

Route::get('/login', Login::class)
    ->middleware('guest')
    ->name('login');

Route::get('register', Register::class)
    ->middleware('guest')
    ->name('register');

Route::get('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login');
})->name('logout');

// Send
Route::get('/send/create/group-{groupId}', SendIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('send.index');

// Group
Route::get('/group-send', GroupSendIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('group-send.index');

// User perfil
Route::get('/perfil/{id?}', PerfilIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('perfil.index');

// User perfil
Route::get('/contatos', ListContatosIndex::class)
    ->middleware(['auth', 'verified'])
    ->name('contatos.index');

require __DIR__.'/auth.php';
