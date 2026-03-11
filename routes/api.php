<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\GatewayController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Global route patterns
|--------------------------------------------------------------------------
*/
Route::pattern('transaction', '[0-9]+');
Route::pattern('client', '[0-9]+');
Route::pattern('product', '[0-9]+');
Route::pattern('user', '[0-9]+');
Route::pattern('gateway', '[0-9]+');

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->as('api.v1.')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Public routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('throttle:api')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->name('auth.login');

        Route::post('/transactions', [TransactionController::class, 'store'])
            ->name('transactions.store');
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Auth
        |--------------------------------------------------------------------------
        */
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');

        Route::get('/user', static function (Request $request) {
            return response()->json([
                'data' => [
                    'id' => $request->user()?->id,
                    'name' => $request->user()?->name,
                    'email' => $request->user()?->email,
                    'role' => $request->user()?->role?->value ?? $request->user()?->role,
                    'is_active' => $request->user()?->is_active,
                    'created_at' => $request->user()?->created_at
                        ?->timezone('America/Sao_Paulo')
                        ?->format('Y-m-d H:i:s'),
                    'updated_at' => $request->user()?->updated_at
                        ?->timezone('America/Sao_Paulo')
                        ?->format('Y-m-d H:i:s'),
                ],
            ]);
        })->name('auth.user');

        /*
        |--------------------------------------------------------------------------
        | Transactions
        |--------------------------------------------------------------------------
        */
        Route::apiResource('transactions', TransactionController::class)
            ->only(['index', 'show'])
            ->names('transactions');

        /*
        |--------------------------------------------------------------------------
        | Clients
        |--------------------------------------------------------------------------
        */
        Route::apiResource('clients', ClientController::class)
            ->only(['index', 'show'])
            ->names('clients');

        /*
        |--------------------------------------------------------------------------
        | Products - leitura autenticada
        |--------------------------------------------------------------------------
        */
        Route::apiResource('products', ProductController::class)
            ->only(['index', 'show'])
            ->names('products');

        /*
        |--------------------------------------------------------------------------
        | Products - gestão
        |--------------------------------------------------------------------------
        */
        Route::middleware('role:ADMIN,MANAGER,FINANCE')->group(function (): void {
            Route::post('/products', [ProductController::class, 'store'])
                ->name('products.store');

            Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])
                ->name('products.update');

            Route::delete('/products/{product}', [ProductController::class, 'destroy'])
                ->name('products.destroy');
        });

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */
        Route::middleware('role:ADMIN,MANAGER')->group(function (): void {
            Route::apiResource('users', UserController::class)
                ->only(['index', 'show', 'store', 'update', 'destroy'])
                ->names('users');

            Route::patch('/users/{user}', [UserController::class, 'update'])
                ->name('users.patch');
        });

        /*
        |--------------------------------------------------------------------------
        | Gateways
        |--------------------------------------------------------------------------
        */
        Route::prefix('gateways')->as('gateways.')->middleware('role:ADMIN')->group(function (): void {
            Route::get('/', [GatewayController::class, 'index'])
                ->name('index');

            Route::get('/{gateway}', [GatewayController::class, 'show'])
                ->name('show');

            Route::patch('/{gateway}/priority', [GatewayController::class, 'updatePriority'])
                ->name('update-priority');

            Route::patch('/{gateway}/active', [GatewayController::class, 'setActive'])
                ->name('set-active');
        });

        /*
        |--------------------------------------------------------------------------
        | Refunds
        |--------------------------------------------------------------------------
        */
        Route::middleware('role:ADMIN,FINANCE')->group(function (): void {
            Route::post('/transactions/{transaction}/refund', [RefundController::class, 'store'])
                ->name('transactions.refund');
        });
    });
});