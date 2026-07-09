<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CustomerDisplayController;
use App\Http\Controllers\BranchController;
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
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware('guest')->group(function () {
    Route::get('/signin', [AuthController::class, 'showSignIn'])->name('signin');
    Route::post('/signin', [AuthController::class, 'signIn'])->name('signin.store');
    Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
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

Route::view('/privacy-policy', 'pages.privacy-policy', [
    'title' => 'Kebijakan Privasi',
])->name('privacy-policy');

Route::view('/terms-of-service', 'pages.terms-of-service', [
    'title' => 'Syarat dan Ketentuan Layanan',
])->name('terms-of-service');

Route::middleware('auth:web,sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/global-search', GlobalSearchController::class)->name('global-search');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::post('/branches/active', [BranchController::class, 'switch'])->name('branches.switch');

    Route::middleware('role:owner,admin')->group(function () {
        Route::get('/billings', [BillingController::class, 'index'])->name('billings');
        Route::post('/billings', [BillingController::class, 'store'])->name('billings.store');
        Route::post('/billings/{billing}/refresh', [BillingController::class, 'refresh'])->name('billings.refresh');
        Route::get('/settings', [SettingController::class, 'index'])->name('settings');
        Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::get('/settings/tokens', [\App\Http\Controllers\DeveloperApiController::class, 'getTokens'])->name('settings.tokens.index');
        Route::post('/settings/tokens', [\App\Http\Controllers\DeveloperApiController::class, 'createToken'])->name('settings.tokens.store');
        Route::delete('/settings/tokens/{id}', [\App\Http\Controllers\DeveloperApiController::class, 'revokeToken'])->name('settings.tokens.destroy');
        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::patch('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
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
            Route::delete('/products/{sku}', [ProductController::class, 'destroy'])->name('products.destroy');
            Route::get('/product-recipes', [ProductRecipeController::class, 'index'])->name('product-recipes');
            Route::post('/product-recipes', [ProductRecipeController::class, 'store'])->name('product-recipes.store');
            Route::get('/product-categories', [ProductCategoryController::class, 'index'])->name('product-categories');
            Route::post('/product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
            Route::patch('/product-categories/{code}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
            Route::delete('/product-categories/{code}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');
            Route::get('/raw-materials', [RawMaterialController::class, 'index'])->name('raw-materials');
            Route::post('/raw-materials', [RawMaterialController::class, 'store'])->name('raw-materials.store');
            Route::post('/raw-materials/{rawMaterial}/stock-in', [RawMaterialController::class, 'stockIn'])->name('raw-materials.stock-in');
            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
            Route::post('/inventory/{sku}/in', [InventoryController::class, 'storeIn'])->name('inventory.in');
            Route::post('/inventory/{sku}/out', [InventoryController::class, 'storeOut'])->name('inventory.out');
            Route::patch('/inventory/{sku}', [InventoryController::class, 'update'])->name('inventory.update');
            Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
            Route::post('/stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
            Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
            Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
            Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
            Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
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
            Route::post('/cashier-shifts/{cashierShift}/force-close', [CashierShiftController::class, 'forceClose'])->name('cashier-shifts.force-close');
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

    Route::prefix('display')->name('display.')->group(function () {
        Route::middleware('role:owner,admin,kasir')->group(function () {
            Route::post('/state', [CustomerDisplayController::class, 'push'])->name('push');
        });
    });
});

Route::prefix('display')->name('display.')->group(function () {
    Route::get('/stand', [CustomerDisplayController::class, 'show'])->name('stand');
    Route::get('/state', [CustomerDisplayController::class, 'state'])->name('state');
});

Route::get('/profile', function () {
    $setting = StoreSetting::current();

    return view('pages.profile', [
        'title' => 'Profil',
        'user' => auth()->user(),
        'setting' => $setting,
        'logoUrl' => $setting->logo_path ? Storage::url($setting->logo_path) : asset('/images/user/owner.jpg'),
    ]);
})->name('profile');
