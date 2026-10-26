<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminMenuController;
use App\Http\Controllers\Admin\AdminMerchantController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPromoController;
use App\Http\Controllers\Admin\AdminTableController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\ChatbotController;

/*
|--------------------------------------------------------------------------
| API Routes - MejaKu
|--------------------------------------------------------------------------
|
| Route API untuk aplikasi pemesanan makanan MejaKu.
| Semua route menggunakan prefix /api secara otomatis.
|
*/

// =============================================
 // Dokumentasi API (Swagger UI, publik)
 // =============================================
Route::get('/docs', [ApiDocsController::class, 'index']);
Route::get('/docs.yaml', [ApiDocsController::class, 'yaml']);
Route::get('/docs.json', [ApiDocsController::class, 'json']);

// =============================================
// Menu & Katalog (rate limit: api default)
// =============================================
Route::get('/menu', [MenuController::class, 'index']);
Route::get('/menu/{slug}', [MenuController::class, 'show']);
Route::post('/menu/{id}/rate', [MenuController::class, 'rate']);
Route::get('/menu/{id}/reviews', [MenuController::class, 'reviews']);
Route::get('/merchants/{id}', [MenuController::class, 'merchant']);
Route::get('/spice-levels', [MenuController::class, 'spiceLevels']);


// =============================================
// Meja (rate limit: api default)
// =============================================
Route::get('/tables', [TableController::class, 'index']);
Route::get('/tables/{code}', [TableController::class, 'show']);

// =============================================
// Order (rate limit: create-order + idempotency)
// =============================================
Route::post('/orders', [OrderController::class, 'store'])
    ->middleware(['throttle:create-order', 'idempotency']);
Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);
// Update status dapur hanya untuk admin (dulu publik/demo).
Route::patch('/orders/{orderNumber}/status', [OrderController::class, 'updateStatus'])
    ->middleware('auth:sanctum');

// =============================================
// Pembayaran (rate limit: payment)
// =============================================
Route::middleware('throttle:payment')->group(function () {
    Route::post('/payments/{orderNumber}', [PaymentController::class, 'store']);
    Route::get('/payments/{orderNumber}/status', [PaymentController::class, 'status']);
    Route::post('/payments/{orderNumber}/simulate', [PaymentController::class, 'simulate']);
    Route::get('/payments/finish', [PaymentController::class, 'finish']);
});

// Webhook Midtrans dibebaskan dari throttle agar notifikasi tidak tertolak.
Route::post('/midtrans/notification', [PaymentController::class, 'notification']);

// =============================================
// Promo (rate limit: api default)
// =============================================
Route::get('/promos', [PromoController::class, 'index']);
Route::post('/promo/validate', [PromoController::class, 'validate']);

// =============================================
// Admin Auth (publik: login)
// =============================================
Route::post('/admin/auth/login', [AdminAuthController::class, 'login']);

// =============================================
// Admin Dashboard (auth:sanctum)
// =============================================
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
    Route::get('/auth/me', [AdminAuthController::class, 'me']);

    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{orderNumber}', [AdminOrderController::class, 'show']);
    Route::patch('/orders/{orderNumber}/preparation', [AdminOrderController::class, 'updatePreparation']);
    Route::patch('/orders/{orderNumber}/payment', [AdminOrderController::class, 'updatePayment']);
    Route::post('/orders/{orderNumber}/cancel', [AdminOrderController::class, 'cancel']);

    Route::get('/tables', [AdminTableController::class, 'index']);
    Route::post('/tables', [AdminTableController::class, 'store']);
    Route::get('/tables/{id}', [AdminTableController::class, 'show']);
    Route::put('/tables/{id}', [AdminTableController::class, 'update']);
    Route::patch('/tables/{id}/toggle', [AdminTableController::class, 'toggle']);
    Route::delete('/tables/{id}', [AdminTableController::class, 'destroy']);

    Route::get('/promos', [AdminPromoController::class, 'index']);
    Route::post('/promos', [AdminPromoController::class, 'store']);
    Route::get('/promos/{id}', [AdminPromoController::class, 'show']);
    Route::put('/promos/{id}', [AdminPromoController::class, 'update']);
    Route::delete('/promos/{id}', [AdminPromoController::class, 'destroy']);

    Route::get('/menu', [AdminMenuController::class, 'index']);
    Route::post('/menu', [AdminMenuController::class, 'store']);
    Route::post('/menu/upload-image', [AdminMenuController::class, 'uploadImage']);
    Route::get('/menu/{id}', [AdminMenuController::class, 'show']);
    Route::put('/menu/{id}', [AdminMenuController::class, 'update']);
    Route::patch('/menu/{id}/toggle', [AdminMenuController::class, 'toggle']);
    Route::delete('/menu/{id}', [AdminMenuController::class, 'destroy']);

    Route::get('/payments', [AdminPaymentController::class, 'index']);

    Route::get('/store/status', [StoreController::class, 'show']);
    Route::put('/store/status', [StoreController::class, 'update']);

    Route::get('/merchants', [AdminMerchantController::class, 'index']);
    Route::post('/merchants', [AdminMerchantController::class, 'store']);
    Route::get('/merchants/{id}', [AdminMerchantController::class, 'show']);
    Route::put('/merchants/{id}', [AdminMerchantController::class, 'update']);
    Route::delete('/merchants/{id}', [AdminMerchantController::class, 'destroy']);
});

// =============================================
// Chatbot - Groq AI (rate limit: chatbot, lebih ketat)
// =============================================
Route::middleware('throttle:chatbot')->group(function () {
    Route::post('/chat', [ChatbotController::class, 'chat']);
    Route::post('/chat/stream', [ChatbotController::class, 'stream']);
});
