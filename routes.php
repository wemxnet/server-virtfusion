<?php

use Extensions\Servers\Virtfusion\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/orders/{order}/virtfusion/login', LoginController::class)
        ->name('virtfusion.login');
});
