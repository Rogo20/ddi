<?php

use App\Http\Controllers\DrugInteractionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/api/interactions', [DrugInteractionController::class, 'check']);
