<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProfileController::class, 'index'])->name('profiles.index');
Route::get('/{username}', [ProfileController::class, 'show'])
    ->where('username', '[A-Za-z0-9._]+')
    ->name('profiles.show');
