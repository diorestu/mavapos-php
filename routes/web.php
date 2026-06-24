<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CashierShiftController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductRecipeController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Storage;

Route::middleware('guest')->group(function () {
    Route::get('/signin', [AuthController::class, 'showSignIn'])->name('signin');
    Route::post('/signin', [AuthController::class, 'signIn'])->name('signin.store');
    Route::get('/signup', [AuthController::class, 'showSignUp'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signUp'])->name('signup.store');
});

Route::post('/pakasir/webhook', [BillingController::class, 'webhook'])->name('pakasir.webhook');

Route::get('/', function () {
    if (auth()->check()) {
        return app(DashboardController::class)->index();
    }
    return view('pages.landing', ['title' => 'MavaPOS - Aplikasi Kasir Online']);
})->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/global-search', GlobalSearchController::class)->name('global-search');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    Route::middleware('role:owner,admin')->group(function () {
        Route::get('/billings', [BillingController::class, 'index'])->name('billings');
        Route::post('/billings', [BillingController::class, 'store'])->name('billings.store');
        Route::post('/billings/{billing}/refresh', [BillingController::class, 'refresh'])->name('billings.refresh');
        Route::get('/settings', [SettingController::class, 'index'])->name('settings');
        Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::middleware('subscription')->group(function () {
        Route::middleware('role:owner,admin,gudang')->group(function () {
            Route::get('/products', [ProductController::class, 'index'])->name('products');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::patch('/products/{sku}', [ProductController::class, 'update'])->name('products.update');
            Route::get('/product-recipes', [ProductRecipeController::class, 'index'])->name('product-recipes');
            Route::post('/product-recipes', [ProductRecipeController::class, 'store'])->name('product-recipes.store');
            Route::get('/product-categories', [ProductCategoryController::class, 'index'])->name('product-categories');
            Route::post('/product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
            Route::patch('/product-categories/{code}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
            Route::get('/raw-materials', [RawMaterialController::class, 'index'])->name('raw-materials');
            Route::post('/raw-materials', [RawMaterialController::class, 'store'])->name('raw-materials.store');
            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
            Route::post('/inventory/{sku}/in', [InventoryController::class, 'storeIn'])->name('inventory.in');
            Route::post('/inventory/{sku}/out', [InventoryController::class, 'storeOut'])->name('inventory.out');
            Route::patch('/inventory/{sku}', [InventoryController::class, 'update'])->name('inventory.update');
        });

        Route::middleware('role:owner,admin,kasir')->group(function () {
            Route::get('/pos', [PosController::class, 'index'])->name('pos');
            Route::post('/pos/shift/start', [PosController::class, 'startShift'])->name('pos.shift.start');
            Route::post('/pos/shift/close', [PosController::class, 'closeShift'])->name('pos.shift.close');
            Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
            Route::get('/cashier-shifts', [CashierShiftController::class, 'index'])->name('cashier-shifts');
            Route::get('/sales', [SaleController::class, 'index'])->name('sales');
        });

        Route::middleware('role:owner,admin')->group(function () {
            Route::get('/customers', [CustomerController::class, 'index'])->name('customers');
            Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::patch('/customers/{code}', [CustomerController::class, 'update'])->name('customers.update');
            Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers');
            Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::patch('/suppliers/{code}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses');
            Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('/reports', [ReportController::class, 'index'])->name('reports');
            Route::get('/reports/download', [ReportController::class, 'download'])->name('reports.download');
        });
    });
    Route::middleware('role:owner,admin')->group(function () {
        Route::get('/print-test', function () {
            return view('pages.print-test', ['title' => 'Test Printing']);
        })->name('print-test');
    });

    Route::get('/calendar', function () {
        return view('pages.calender', ['title' => 'Kalender']);
    })->name('calendar');

    Route::get('/profile', function () {
        $setting = StoreSetting::current();

        return view('pages.profile', [
            'title' => 'Profil',
            'user' => auth()->user(),
            'setting' => $setting,
            'logoUrl' => $setting->logo_path ? Storage::url($setting->logo_path) : asset('/images/user/owner.jpg'),
        ]);
    })->name('profile');

    Route::get('/form-elements', function () {
        return view('pages.form.form-elements', ['title' => 'Elemen Formulir']);
    })->name('form-elements');

    Route::get('/basic-tables', function () {
        return view('pages.tables.basic-tables', ['title' => 'Tabel Dasar']);
    })->name('basic-tables');

    Route::get('/blank', function () {
        return view('pages.blank', ['title' => 'Halaman Kosong']);
    })->name('blank');

    Route::get('/error-404', function () {
        return view('pages.errors.error-404', ['title' => 'Error 404']);
    })->name('error-404');

    Route::get('/line-chart', function () {
        return view('pages.chart.line-chart', ['title' => 'Grafik Garis']);
    })->name('line-chart');

    Route::get('/bar-chart', function () {
        return view('pages.chart.bar-chart', ['title' => 'Grafik Batang']);
    })->name('bar-chart');

    Route::get('/alerts', function () {
        return view('pages.ui-elements.alerts', ['title' => 'Peringatan']);
    })->name('alerts');

    Route::get('/avatars', function () {
        return view('pages.ui-elements.avatars', ['title' => 'Avatar']);
    })->name('avatars');

    Route::get('/badge', function () {
        return view('pages.ui-elements.badges', ['title' => 'Badge']);
    })->name('badges');

    Route::get('/buttons', function () {
        return view('pages.ui-elements.buttons', ['title' => 'Tombol']);
    })->name('buttons');

    Route::get('/image', function () {
        return view('pages.ui-elements.images', ['title' => 'Gambar']);
    })->name('images');

    Route::get('/videos', function () {
        return view('pages.ui-elements.videos', ['title' => 'Video']);
    })->name('videos');
});
