<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Konfigurasi rate limiting untuk API.
     */
    protected function configureRateLimiting(): void
    {
        // Rate limit umum API: 60 request per menit
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Rate limit ketat untuk chatbot: 10 request per menit
        RateLimiter::for('chatbot', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->ip()
            )->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak permintaan chatbot. Silakan tunggu sebentar.',
                ], 429);
            });
        });

        // Rate limit untuk pembuatan order: 5 per menit
        RateLimiter::for('create-order', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->ip()
            )->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak pembuatan pesanan. Silakan tunggu sebentar.',
                ], 429);
            });
        });

        // Rate limit untuk pembayaran: 30 per menit (kasir + polling customer)
        RateLimiter::for('payment', function (Request $request) {
            return Limit::perMinute(30)->by(
                $request->ip()
            )->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak permintaan pembayaran. Silakan tunggu sebentar.',
                ], 429);
            });
        });
    }
}
