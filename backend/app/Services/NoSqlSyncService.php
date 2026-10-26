<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Mongo\MenuReviewDocument;
use App\Models\Mongo\OrderAuditLog;
use Illuminate\Support\Facades\Log;

class NoSqlSyncService
{
    /**
     * Memeriksa apakah ekstensi PHP MongoDB dan konfigurasi siap digunakan.
     */
    public function isAvailable(): bool
    {
        return extension_loaded('mongodb') && !empty(config('database.connections.mongodb'));
    }

    /**
     * Menyimpan ulasan ke collection MongoDB secara fleksibel / non-relasional.
     */
    public function recordReview(MenuItem $item, array $reviewData): ?MenuReviewDocument
    {
        if (!$this->isAvailable()) {
            Log::info('MongoDB extension belum aktif di PHP CLI. Melewati sync NoSQL review.');
            return null;
        }

        try {
            return MenuReviewDocument::create([
                'menu_item_id' => $item->id,
                'menu_name' => $item->name,
                'order_number' => $reviewData['order_number'] ?? null,
                'customer_name' => $reviewData['customer_name'] ?? 'Pelanggan',
                'rating' => (int) ($reviewData['rating'] ?? 5),
                'review' => $reviewData['review'] ?? null,
                'metadata' => [
                    'base_price' => $item->base_price,
                    'created_at_iso' => now()->toIso8601String(),
                    'source' => 'web_customer',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal sinkronisasi ulasan ke MongoDB: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mencatat audit log transaksi pesanan ke collection MongoDB.
     */
    public function recordOrderAudit(string $orderNumber, string $event, array $details = []): ?OrderAuditLog
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            return OrderAuditLog::create([
                'order_number' => $orderNumber,
                'event' => $event,
                'details' => $details,
                'logged_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit log ke MongoDB: ' . $e->getMessage());
            return null;
        }
    }
}
