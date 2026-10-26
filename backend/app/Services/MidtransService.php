<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Midtrans\Config as MidtransConfig;
use Midtrans\CoreApi as MidtransCoreApi;
use Midtrans\Snap as MidtransSnap;
use Midtrans\Transaction as MidtransTransaction;

/**
 * Integrasi Midtrans Snap (QRIS, VA bank, e-wallet, kartu).
 *
 * Ketika kredensial belum diisi, service nonaktif dan alur
 * pembayaran fallback ke mode demo lokal via PaymentService.
 */
class MidtransService
{
    public function isEnabled(): bool
    {
        return config('midtrans.server_key') !== ''
            && config('midtrans.client_key') !== '';
    }

    /**
     * Konfigurasi SDK Midtrans dari config/midtrans.php.
     */
    public function configure(): void
    {
        MidtransConfig::$serverKey = (string) config('midtrans.server_key');
        MidtransConfig::$clientKey = (string) config('midtrans.client_key');
        MidtransConfig::$isProduction = (bool) config('midtrans.is_production', false);
        MidtransConfig::$isSanitized = (bool) config('midtrans.is_sanitized', true);
        MidtransConfig::$is3ds = (bool) config('midtrans.is_3ds', true);
    }

    /**
     * Charge langsung via Core API (tanpa popup Snap).
     *
     * - 'qris': mengembalikan qr_string untuk dirender jadi gambar QR.
     * - 'va': butuh $bank (bca/bri/bni/permata), mengembalikan nomor VA.
     *
     * @return array{success: bool, message: string}
     */
    public function chargeDirect(Order $order, Payment $payment, string $channel, ?string $bank = null): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Midtrans belum dikonfigurasi.'];
        }

        if ($channel === 'va' && !in_array($bank, ['bca', 'bri', 'bni', 'permata'], true)) {
            return ['success' => false, 'message' => 'Pilih bank VA: BCA, BRI, BNI, atau Permata.'];
        }

        $this->configure();

        // order_id Midtrans harus unik per charge. Charge ulang dengan
        // channel/bank yang sama sebelumnya memakai string deterministik
        // yang sama (base-channel-bank) sehingga Midtrans menolak dengan
        // 406 "conflict with the current state". Selalu bubuhkan suffix unik
        // agar tombol "Coba Lagi" / ganti bank tidak pernah menabrak order_id lama.
        $base = $order->order_number.'-'.$payment->id;
        $midtransOrderId = $base;
        if ($payment->midtrans_order_id) {
            $midtransOrderId = $base.'-'.$channel.($bank ? '-'.$bank : '').'-'.substr((string) time(), -6).Str::upper(Str::random(4));
        }

        $params = [
            'payment_type' => $channel === 'va' ? 'bank_transfer' : 'qris',
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => (int) $payment->amount,
            ],
            'item_details' => $this->buildItemDetails($order),
            'customer_details' => [
                'first_name' => mb_substr($order->notes ?: 'Pelanggan MejaKu', 0, 50),
            ],
        ];

        if ($channel === 'va') {
            $params['bank_transfer'] = ['bank' => $bank];
        }

        try {
            $result = (array) MidtransCoreApi::charge($params);

            $update = [
                'midtrans_order_id' => $midtransOrderId,
                'transaction_id' => $result['transaction_id'] ?? null,
                'payment_type' => $result['payment_type'] ?? ($channel === 'va' ? 'bank_transfer' : 'qris'),
                'midtrans_response' => $result,
            ];

            if ($channel === 'qris' && !empty($result['qr_string'])) {
                $update['qr_data'] = $result['qr_string'];
                $update['va_number'] = null;
                $update['va_bank'] = null;
            }

            $va = $result['va_numbers'][0] ?? null;
            if ($va) {
                $va = (array) $va;
                $update['va_number'] = $va['va_number'] ?? null;
                $update['va_bank'] = $va['bank'] ?? $bank;
                $update['qr_data'] = null;
            } elseif (!empty($result['permata_va_number'])) {
                // Permata mengembalikan nomor flat, bukan array va_numbers.
                $update['va_number'] = $result['permata_va_number'];
                $update['va_bank'] = 'permata';
                $update['qr_data'] = null;
            } elseif (!empty($result['bill_key'])) {
                // Mandiri e-channel: gabung biller + bill key.
                $update['va_number'] = trim(($result['biller_code'] ?? '') . ' ' . $result['bill_key']);
                $update['va_bank'] = 'mandiri';
                $update['qr_data'] = null;
            }

            $payment->update($update);

            return ['success' => true, 'message' => 'Tagihan Midtrans dibuat.'];
        } catch (\Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Gagal membuat tagihan Midtrans: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Buat transaksi Snap untuk payment yang masih pending.
     *
     * @param list<string>|null $enabledPayments Batasi metode di popup Snap
     *   (cth: ['qris'] atau daftar VA bank). Null = semua metode aktif.
     * @return array{success: bool, message: string}
     */
    public function createSnapTransaction(Order $order, Payment $payment, ?array $enabledPayments = null): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Midtrans belum dikonfigurasi.'];
        }

        $this->configure();

        // order_id Midtrans harus unik per percobaan bayar.
        $midtransOrderId = $payment->midtrans_order_id
            ?: $order->order_number.'-'.$payment->id;

        $params = [
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => (int) $payment->amount,
            ],
            'item_details' => $this->buildItemDetails($order),
            'customer_details' => [
                'first_name' => mb_substr($order->notes ?: 'Pelanggan MejaKu', 0, 50),
            ],
            'callbacks' => [
                'finish' => rtrim((string) config('app.url'), '/').'/api/payments/finish',
            ],
        ];

        if ($enabledPayments !== null && $enabledPayments !== []) {
            $params['enabled_payments'] = array_values($enabledPayments);
        }

        try {
            $result = MidtransSnap::createTransaction($params);

            $payment->update([
                'snap_token' => $result->token,
                'redirect_url' => $result->redirect_url,
                'midtrans_order_id' => $midtransOrderId,
            ]);

            return ['success' => true, 'message' => 'Transaksi Midtrans dibuat.'];
        } catch (\Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Gagal membuat transaksi Midtrans: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Ambil status terbaru dari Midtrans lalu sinkronkan ke payment/order.
     */
    public function syncFromRemote(Order $order, Payment $payment): ?Payment
    {
        if (!$this->isEnabled() || !$payment->midtrans_order_id) {
            return $payment;
        }

        $this->configure();

        try {
            $remote = (array) MidtransTransaction::status($payment->midtrans_order_id);
        } catch (\Exception $e) {
            report($e);

            return $payment;
        }

        $this->applyRemoteStatus($order, $payment, $remote);

        return $payment->fresh();
    }

    /**
     * Tangani webhook Midtrans (HTTP notification).
     *
     * @return array{success: bool, message: string, payment?: Payment}
     */
    public function handleNotification(array $payload): array
    {
        $orderId = (string) ($payload['order_id'] ?? '');

        if ($orderId === '') {
            return ['success' => false, 'message' => 'order_id kosong pada notifikasi.'];
        }

        if (!$this->verifySignature($payload)) {
            return ['success' => false, 'message' => 'Signature Midtrans tidak valid.'];
        }

        $payment = Payment::where('midtrans_order_id', $orderId)->first();

        if (!$payment) {
            return ['success' => false, 'message' => 'Pembayaran tidak ditemukan.'];
        }

        $order = $payment->order;
        $this->applyRemoteStatus($order, $payment, $payload);

        return [
            'success' => true,
            'message' => 'Notifikasi diproses.',
            'payment' => $payment->fresh(),
        ];
    }

    /**
     * Verifikasi signature_key = SHA512(order_id+status_code+gross_amount+serverKey).
     */
    public function verifySignature(array $payload): bool
    {
        $expected = hash('sha512', implode('', [
            (string) ($payload['order_id'] ?? ''),
            (string) ($payload['status_code'] ?? ''),
            (string) ($payload['gross_amount'] ?? ''),
            (string) config('midtrans.server_key'),
        ]));

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Helper internal
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $remote
     */
    protected function applyRemoteStatus(Order $order, Payment $payment, array $remote): void
    {
        $transactionStatus = (string) ($remote['transaction_status'] ?? '');
        $fraudStatus = (string) ($remote['fraud_status'] ?? '');
        $paymentType = (string) ($remote['payment_type'] ?? '');

        $mapped = $this->mapTransactionStatus($transactionStatus, $fraudStatus);

        $payment->update([
            'status' => $mapped,
            'transaction_id' => $remote['transaction_id'] ?? $payment->transaction_id,
            'payment_type' => $paymentType !== '' ? $paymentType : $payment->payment_type,
            'fraud_status' => $fraudStatus !== '' ? $fraudStatus : $payment->fraud_status,
            'midtrans_response' => $remote,
            'paid_at' => $mapped === 'paid' ? ($payment->paid_at ?: Carbon::now()) : $payment->paid_at,
        ]);

        $order->update([
            'payment_status' => $mapped === 'paid' ? 'paid'
                : ($mapped === 'pending' ? 'pending'
                : ($mapped === 'expired' ? 'expired' : 'failed')),
            'preparation_status' => $mapped === 'paid' && $order->preparation_status === 'received'
                ? 'preparing'
                : $order->preparation_status,
        ]);
    }

    protected function mapTransactionStatus(string $transactionStatus, string $fraudStatus): string
    {
        return match ($transactionStatus) {
            'capture' => $fraudStatus === 'challenge' ? 'pending' : 'paid',
            'settlement' => 'paid',
            'pending' => 'pending',
            'expire' => 'expired',
            'cancel', 'deny', 'refund', 'partial_refund' => 'failed',
            default => 'pending',
        };
    }

    /**
     * @return list<array{ id: string, price: int, quantity: int, name: string }>
     */
    protected function buildItemDetails(Order $order): array
    {
        $order->loadMissing(['items.menuItem', 'items.portion']);

        $items = [];
        foreach ($order->items as $item) {
            $name = $item->menuItem?->name ?? 'Item';
            if ($item->portion) {
                $name .= ' ('.$item->portion->name.')';
            }
            $items[] = [
                'id' => 'item-'.$item->id,
                'price' => (int) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'name' => mb_substr($name, 0, 50),
            ];
        }

        if ($items === []) {
            $items[] = [
                'id' => 'order-'.$order->id,
                'price' => (int) $order->total,
                'quantity' => 1,
                'name' => mb_substr('Pesanan '.$order->order_number, 0, 50),
            ];

            return $items;
        }

        // Midtrans menolak bila gross_amount != jumlah item (service fee,
        // pajak, diskon membuat selisih) → gabung jadi satu item order.
        $sum = 0;
        foreach ($items as $it) {
            $sum += $it['price'] * $it['quantity'];
        }

        if ($sum !== (int) $order->total) {
            return [[
                'id' => 'order-'.$order->id,
                'price' => (int) $order->total,
                'quantity' => 1,
                'name' => mb_substr('Pesanan '.$order->order_number, 0, 50),
            ]];
        }

        return $items;
    }
}
