<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');

Route::livewire('/dashboard', 'dashboard')
    ->middleware('auth')
    ->name('dashboard');

Route::redirect('/', '/dashboard');
