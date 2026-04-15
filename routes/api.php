<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['auth:sanctum', 'sanctum.on_job'])->get('/user', [UserController::class, 'me']);

Route::prefix('auth')->group(function () {
    Route::post('/invite-code/verify', [AuthController::class, 'verifyInviteCode']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/password', [AuthController::class, 'password'])->middleware(['auth:sanctum', 'sanctum.on_job']);
});

Route::middleware(['auth:sanctum', 'sanctum.on_job'])->prefix('presence')->group(function () {
    Route::post('/arrival', [PresenceController::class, 'arrival']);
    Route::post('/outing/start', [PresenceController::class, 'outingStart']);
    Route::post('/outing/end', [PresenceController::class, 'outingEnd']);
    Route::post('/offwork', [PresenceController::class, 'offwork']);
    Route::get('/today', [PresenceController::class, 'today']);
});
