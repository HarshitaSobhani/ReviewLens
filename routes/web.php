<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'landing'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/demo', [DemoController::class, 'store'])->name('demo.store');

    Route::resource('products', ProductController::class);

    Route::get('/products/{product}/imports/create', [ReviewImportController::class, 'create'])->name('imports.create');
    Route::post('/products/{product}/imports', [ReviewImportController::class, 'store'])->name('imports.store')->middleware('throttle:imports');
    Route::get('/imports/{import}', [ReviewImportController::class, 'show'])->name('imports.show');
    Route::put('/imports/{import}/mapping', [ReviewImportController::class, 'mapping'])->name('imports.mapping');
    Route::post('/imports/{import}/confirm', [ReviewImportController::class, 'confirm'])->name('imports.confirm')->middleware('throttle:imports');
    Route::delete('/imports/{import}', [ReviewImportController::class, 'destroy'])->name('imports.destroy');

    Route::get('/products/{product}/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');

    Route::get('/analyses', [AnalysisController::class, 'index'])->name('analyses.index');
    Route::post('/products/{product}/analyses', [AnalysisController::class, 'store'])->name('analyses.store')->middleware('throttle:analyses');
    Route::get('/analyses/{analysis}', [AnalysisController::class, 'show'])->name('analyses.show');
    Route::post('/analyses/{analysis}/reanalyze', [AnalysisController::class, 'reanalyze'])->name('analyses.reanalyze')->middleware('throttle:analyses');
    Route::delete('/analyses/{analysis}', [AnalysisController::class, 'destroy'])->name('analyses.destroy');

    Route::get('/analyses/{analysis}/export/report', [ExportController::class, 'report'])->name('exports.report')->middleware('throttle:exports');
    Route::get('/analyses/{analysis}/export/{type}', [ExportController::class, 'csv'])->name('exports.csv')->middleware('throttle:exports');

    Route::get('/compare', [ComparisonController::class, 'index'])->name('compare');
});
