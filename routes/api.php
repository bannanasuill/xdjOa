<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PresenceController;
use Illuminate\Http\Request;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->prefix('presence')->group(function () {
    Route::post('/arrival', [PresenceController::class, 'arrival']);
    Route::post('/outing/start', [PresenceController::class, 'outingStart']);
    Route::post('/outing/end', [PresenceController::class, 'outingEnd']);
    Route::get('/today', [PresenceController::class, 'today']);
});
