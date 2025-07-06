<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GarageController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\TopUpController;

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

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/garages', [GarageController::class, 'index']);
Route::get('/services', [ServiceController::class, 'index']);
Route::post('/booking', [TransactionController::class, 'store']);
Route::get('/transactions', [TransactionController::class, 'index']);
Route::post('/transactions', [TransactionController::class, 'store']);
Route::get('/transactions/user/{userId}', [TransactionController::class, 'getUserTransactions']);
Route::get('/emergency-services', [EmergencyController::class, 'getServices']);
Route::post('/emergency-requests', [EmergencyController::class, 'createRequest']);
Route::get('/emergency-requests/user/{userId}', [EmergencyController::class, 'getUserRequests']);
Route::put('/emergency-requests/{id}/status', [EmergencyController::class, 'updateRequestStatus']);
Route::get('/user/saldo', [TopUpController::class, 'getSaldo']);    
Route::get('/user/saldo', [TopUpController::class, 'getSaldo']);    
Route::prefix('topup')->group(function () {
    Route::post('/create', [TopUpController::class, 'createTopUp']);
    Route::get('/status', [TopUpController::class, 'checkPaymentStatus']);
});

Route::post('/topup/notification', [TopUpController::class, 'handleNotification']);
