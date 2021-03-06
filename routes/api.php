<?php

use App\Http\Controllers\HostReservationsController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OfficeImageController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserReservationsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/tags', TagController::class);
Route::get('/offices', [OfficeController::class, 'index']);
Route::get('/offices/{office}', [OfficeController::class, 'show']);
Route::post('/offices', [OfficeController::class, 'create'])
    ->middleware('auth:sanctum', 'verified');
Route::put('/offices/{office}', [OfficeController::class, 'update'])
    ->middleware('auth:sanctum', 'verified');
Route::delete('/offices/{office}', [OfficeController::class, 'destroy'])
    ->middleware('auth:sanctum', 'verified');

Route::post('/offices/{office}/image', [OfficeImageController::class, 'store'])
    ->middleware('auth:sanctum', 'verified');
Route::delete('/offices/{office}/image/{image:id}', [OfficeImageController::class, 'destroy'])
    ->middleware('auth:sanctum', 'verified');

Route::get('/reservations', [UserReservationsController::class, 'index'])
    ->middleware('auth:sanctum', 'verified');
Route::post('/reservations', [UserReservationsController::class, 'create'])
    ->middleware('auth:sanctum', 'verified');

Route::get('/host/reservations', [HostReservationsController::class, 'index'])
    ->middleware('auth:sanctum', 'verified');

