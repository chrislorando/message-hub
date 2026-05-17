<?php

use App\Http\Controllers\FonnteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::match(['get', 'post'], 'fonnte/webhook', [FonnteController::class, 'webhook'])->name('fonnte.webhook');
Route::match(['post'], 'fonnte/answer', [FonnteController::class, 'answer'])->name('fonnte.answer');
Route::match(['post'], 'fonnte/summary', [FonnteController::class, 'summary'])->name('fonnte.summary');
