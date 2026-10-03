<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\TelegramController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — AquaOptom Wholesale Beverage CRM
|--------------------------------------------------------------------------
*/

// Authentication (Public)
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login']);
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout')->middleware('auth');

// Protected CRM Workspace (Session Auth + Active User Status)
Route::middleware(['auth', 'active'])->group(function () {
    // 1. Root and Dashboard
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });
    Route::get('/dashboard', function () {
        return view('pages.dashboard');
    })->name('dashboard');

    // 2. Savdo — Amalga oshgan savdolar tarixi va tahlili
    Route::get('/savdo', function () {
        return view('pages.sales-history');
    })->name('sales.history');

    // 3. Sotuv — Yangi optom savdo operatsiyasi
    Route::get('/sotuv', function () {
        return view('pages.sales-pos');
    })->name('sales.pos');

    // 4. Ombor — Tovar kirimi va qoldiqlar boshqaruvi
    Route::get('/ombor', function () {
        return view('pages.inventory');
    })->name('inventory.index');

    // 5. Qarzdorliklar — Signed hisob daftari
    Route::get('/qarzdorliklar', function () {
        return view('pages.debts');
    })->name('debts.index');

    // 6. Hisobotlar (Ruxsatli: view_reports)
    Route::get('/hisobotlar', function () {
        return view('pages.reports');
    })->name('reports.index')->middleware('permission:view_reports');

    // 7. Kassa va xarajatlar (Ruxsatli: view_cash)
    Route::get('/kassa', function () {
        return view('pages.cash');
    })->name('cash.index')->middleware('permission:view_cash');

    // 8. Admin panel (Faqat OWNER va ADMIN)
    Route::middleware('role:OWNER,ADMIN')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/opening-balances', fn () => view('pages.admin-opening-balances'))->name('opening-balances');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::post('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('users.toggle-status');
    });

    // Backward compatibility redirects for prototype URLs
    Route::get('/inward', fn () => redirect()->route('inventory.index'));
    Route::get('/pos', fn () => redirect()->route('sales.pos'));
    Route::get('/calculator', fn () => redirect()->route('inventory.index'));
    Route::get('/catalog', fn () => redirect()->route('inventory.index'));
    Route::get('/finance', fn () => redirect()->route('cash.index'));
});

// Telegram Bot Webhook & Setup Routes
Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])
    ->withoutMiddleware([ValidateCsrfToken::class]);
Route::get('/telegram/set-commands', [TelegramController::class, 'setCommands'])
    ->middleware(['auth', 'role:OWNER']);
