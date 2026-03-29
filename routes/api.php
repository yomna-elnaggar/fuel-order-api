<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Driver\FuelOrderController as DriverFuelOrderController;
use App\Http\Controllers\Api\ServiceProvider\FuelOrderController as ServiceProviderFuelOrderController;



// Driver Routes
Route::prefix('driver')->group(function () {
    Route::get('/fuel-orders', [DriverFuelOrderController::class, 'index']);
    Route::get('/fuel-orders/{id}', [DriverFuelOrderController::class, 'show']);
    Route::post('/fuel-orders/finish-order', [DriverFuelOrderController::class, 'finishOrder']);
    Route::post('/create-fuel-orders', [DriverFuelOrderController::class, 'createFuelOrder']);
    // Route::post('/request-additional-today-limit', [DriverFuelOrderController::class, 'requestAdditionalLimit']);
});

// Service Provider Routes
Route::prefix('service-providers')->group(function () {
    Route::prefix('fuel-orders')->group(function () {
        Route::post('/scan-vehicle-qrcode', [ServiceProviderFuelOrderController::class, 'scanQRForVehicles']);
        Route::post('/scan-qrcode', [ServiceProviderFuelOrderController::class, 'scanQRData']);
        Route::post('/create-order', [ServiceProviderFuelOrderController::class, 'createFuelOrder']);
        Route::get('/', [ServiceProviderFuelOrderController::class, 'index']);
        Route::get('/{id}', [ServiceProviderFuelOrderController::class, 'show']);
        Route::post('/receive-order', [ServiceProviderFuelOrderController::class, 'receiveOrder']);
        Route::post('/sent-otp-complete-order', [ServiceProviderFuelOrderController::class, 'sendOtpToConfirmOrder']);
        Route::post('/complete-order', [ServiceProviderFuelOrderController::class, 'completeOrder']);
        Route::post('/cancel-order', [ServiceProviderFuelOrderController::class, 'cancelOrder']);
    });
});

// Admin Routes
Route::prefix('admin')->group(function () {
    Route::get('fuel-orders/create', [\App\Http\Controllers\Api\Admin\FuelOrderController::class, 'create']);
    Route::apiResource('fuel-orders', \App\Http\Controllers\Api\Admin\FuelOrderController::class);
});

// Company Routes
Route::prefix('company')->group(function () {
    Route::apiResource('fuel-orders', \App\Http\Controllers\Api\Company\FuelOrderController::class);
});

// Service Provider Dashboard Routes
Route::prefix('service-provider/dashboard')->group(function () {
    Route::apiResource('fuel-orders', \App\Http\Controllers\Api\ServiceProvider\Dashboard\FuelOrderController::class);
});

