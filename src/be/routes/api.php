<?php

use App\Http\Controllers\Company\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/company/users/export', [UserController::class, 'export']);
Route::post('/company/users/import', [UserController::class, 'import']);

Route::apiResource('/company/users', UserController::class);
