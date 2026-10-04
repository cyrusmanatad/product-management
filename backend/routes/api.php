<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ForumCommentController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\ProductInventoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::pattern('vendor', '[a-z0-9]+(?:-[a-z0-9]+)*');

Route::prefix('v1')->group(function () {
    Route::get('homepage/stores', [HomepageController::class, 'stores']);
    Route::get('homepage/featured-products', [HomepageController::class, 'featured']);
    Route::post('auth/forgot-password', [PasswordResetController::class, 'request'])->middleware('throttle:5,1');
    Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->middleware('throttle:10,1');
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->middleware('throttle:5,1');

    Route::prefix('stores/{slug}')->middleware('vendor:store')->group(function () {
        Route::get('/', [VendorController::class, 'storeInfo']);
        Route::get('catalog/products', [CatalogController::class, 'products']);
        Route::get('catalog/products/{productSlug}', [CatalogController::class, 'show']);
        Route::get('images/{image}', [ProductImageController::class, 'storefront'])->whereNumber('image');
        Route::get('catalog/categories', [CatalogController::class, 'categories']);
        Route::middleware(['auth:api', 'active'])->group(function () {
            Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:30,1');
            Route::get('orders', [CheckoutController::class, 'index']);
        });
    });

    Route::middleware(['auth:api', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::get('users/me', [AuthController::class, 'me']);
        Route::get('me/vendors', [VendorController::class, 'memberships']);
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);

        Route::prefix('platform')->middleware('platform')->group(function () {
            Route::get('featured-products', [HomepageController::class, 'selections']);
            Route::get('vendors/{vendor:slug}/feature-candidates', [HomepageController::class, 'candidates']);
            Route::post('vendors/{vendor:slug}/featured-products', [HomepageController::class, 'store']);
            Route::delete('vendors/{vendor:slug}/featured-products/{productId}', [HomepageController::class, 'destroy'])->whereNumber('productId');
            Route::get('vendors', [VendorController::class, 'index']);
            Route::post('vendors', [VendorController::class, 'store']);
            Route::patch('vendors/{vendor:slug}', [VendorController::class, 'update']);
            Route::apiResource('forums', ForumController::class);
            Route::apiResource('forums.comments', ForumCommentController::class);
        });

        Route::prefix('vendors/{vendor}')->middleware('vendor')->group(function () {
            Route::get('me', [VendorController::class, 'permissions']);
            Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->middleware('permission:edit orders');
            Route::post('orders/{order}/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->middleware('permission:edit orders');
            Route::middleware('permission:view users')->group(function () {
                Route::get('users/total', [UserController::class, 'total']);
                Route::get('users', [UserController::class, 'index']);
            });
            Route::post('users', [UserController::class, 'store'])->middleware('permission:create users');
            Route::patch('users/{user}/role', [UserController::class, 'update_role'])->middleware('permission:edit users');
            Route::patch('users/{user}/status', [UserController::class, 'update_status'])->middleware('permission:edit users');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:delete users');

            Route::middleware('permission:view customers')->group(function () {
                Route::get('customers/total', [CustomerController::class, 'total']);
                Route::get('customers', [CustomerController::class, 'index']);
                Route::get('customers/{user}', [CustomerController::class, 'show']);
            });

            Route::middleware('permission:view roles-permission')->group(function () {
                Route::get('roles/permissions', [RoleController::class, 'permissions']);
                Route::get('roles', [RoleController::class, 'index']);
            });
            Route::post('roles', [RoleController::class, 'store'])->middleware('permission:create roles-permission');
            Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:edit roles-permission');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:delete roles-permission');

            Route::middleware('permission:view products')->group(function () {
                Route::get('products', [ProductController::class, 'index']);
                Route::get('products/{product}', [ProductController::class, 'show']);
                Route::get('categories', [CategoryController::class, 'index']);
                Route::prefix('inventory')->group(function () {
                    Route::get('total', [ProductInventoryController::class, 'total']);
                    Route::get('sales', [ProductInventoryController::class, 'sales']);
                    Route::get('stocks', [ProductInventoryController::class, 'stocks']);
                    Route::get('unavailable', [ProductInventoryController::class, 'unavailable']);
                });
            });
            Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:create products');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware('permission:edit products');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:delete products');
            Route::get('products/{product}/images', [ProductImageController::class, 'index'])->middleware('permission:view products');
            Route::get('products/{product}/images/{image}/file', [ProductImageController::class, 'preview'])->middleware('permission:view products');
            Route::post('products/{product}/images', [ProductImageController::class, 'store'])->middleware(['permission:edit products', 'throttle:30,1']);
            Route::patch('products/{product}/images/{image}/primary', [ProductImageController::class, 'primary'])->middleware('permission:edit products');
            Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->middleware('permission:edit products');
            Route::post('products', [ProductController::class, 'store'])->middleware('permission:create products');
            Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->middleware('permission:edit products');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:delete products');

            Route::middleware('permission:view orders')->group(function () {
                Route::get('orders/total', [OrderController::class, 'total']);
                Route::get('orders/export', [OrderController::class, 'export']);
                Route::get('orders', [OrderController::class, 'index']);
            });
            Route::post('orders', [OrderController::class, 'store'])->middleware('permission:create orders');
            Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update'])->middleware('permission:edit orders');
            Route::delete('orders/{order}', [OrderController::class, 'destroy'])->middleware('permission:delete orders');

            Route::prefix('analytics')->middleware('permission:view analytics')->group(function () {
                Route::get('revenue', [AnalyticsController::class, 'revenue']);
                Route::get('categories', [AnalyticsController::class, 'categories']);
                Route::get('kpi', [AnalyticsController::class, 'kpi']);
            });
        });
    });
});
