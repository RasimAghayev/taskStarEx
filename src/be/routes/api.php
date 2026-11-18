<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Company\UserAuditController;
use App\Http\Controllers\Company\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Authentication (Phase 9) -----------------------------------------
// Token-based (Laravel Sanctum personal access tokens), not SPA/cookie
// stateful auth. "login" is a dedicated, tighter rate limiter (brute
// force target); the general "api" limiter (bootstrap/app.php) still
// applies on top of it.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// --- Company user registry (the repo's real business domain) ---------
// Every route below was, before Phase 9, reachable with zero
// authentication/authorization -- full PII (name/surname/father/dob/
// email), fully CRUD-able by anyone who could reach the API. All of it
// now requires a valid Sanctum token. Export/import/audit are registered
// before apiResource(): they are literal/nested routes, and apiResource's
// {fin} binding on show() would otherwise swallow them.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/company/users/export', [UserController::class, 'export']);
    Route::post('/company/users/import', [UserController::class, 'import'])
        ->middleware('throttle:import');
    Route::get('/company/users/{fin}/audit', [UserAuditController::class, 'index']);

    Route::apiResource('/company/users', UserController::class);
});
