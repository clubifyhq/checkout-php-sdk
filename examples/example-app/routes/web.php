<?php

use App\Http\Controllers\ClubifyDemoController;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Rotas do Clubify Checkout SDK Demo
Route::prefix('clubify')->group(function () {
    Route::get('/', [ClubifyDemoController::class, 'index'])->name('clubify.demo');
    Route::get('/status', [ClubifyDemoController::class, 'status'])->name('clubify.status');
    Route::get('/test-products', [ClubifyDemoController::class, 'testProducts'])->name('clubify.test.products');
    Route::get('/test-checkout', [ClubifyDemoController::class, 'testCheckout'])->name('clubify.test.checkout');
    Route::get('/test-organization', [ClubifyDemoController::class, 'testOrganization'])->name('clubify.test.organization');

    // Rotas da página de testes completos
    Route::get('/test-all-methods', [ClubifyDemoController::class, 'testAllMethodsPage'])->name('clubify.test.all.page');
    Route::post('/test-all-methods', [ClubifyDemoController::class, 'runAllTests'])->name('clubify.test.all.run');
    Route::post('/test-module/{module}', [ClubifyDemoController::class, 'testModule'])->name('clubify.test.module');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});

require __DIR__.'/auth.php';
