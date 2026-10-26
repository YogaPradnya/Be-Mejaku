<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;

class PaymentService
{
    public function __construct(protected MidtransService $midtransService) {}

    /**
     * Buat pembayaran baru untuk order.
     *
     * Bila Midtrans dikonfigurasi (MIDTRANS_SERVER_KEY terisi),
     * transaksi Snap dibuat dan snap_token dikembalikan ke client.
     * $channel 'qris'/'va' membatasi metode yang tampil di popup Snap.
     * Bila belum, fallback ke mode demo lokal (QR dummy).
     *
     * @return array{success: bool, message: string, payment?: Payment}
     */
    public function createPayment(Order $order, string $method, ?string $channel = null, ?string $bank = null): array
    {
        if (!in_array($method, ['qris', 'bank_transfer', 'e_wallet', 'midtrans', 'cashier'], true)) {
            return [
                'success' => false,
                'message' => 'Metode pembayaran tidak valid. Pilih: qris, bank_transfer, e_wallet, midtrans, atau cashier.',
            ];
        }

        // Cek pembayaran aktif yang sudah ada (terbaru dulu)
        $existingPayment = Payment::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'paid'])
            ->latest()
            ->first();

        if ($existingPayment) {
            if ($existingPayment->isPaid()) {
                return [
                    'success' => false,
                    'message' => 'Pesanan sudah dibayar.',
                ];
            }

            // Auto-expire jika sudah lewat waktu
            if ($existingPayment->isExpired()) {
                $existingPayment->update(['status' => 'expired']);
                $order->update(['payment_status' => 'expired']);
            } else {
                // Pembayaran kasir tidak memanggil Midtrans. Bila sebelumnya
                // pelanggan memilih QRIS/VA, ubah payment yang sama menjadi kasir
                // agar dashboard tidak memiliki dua tagihan pending.
                if ($method === 'cashier') {
                    if ($existingPayment->method === 'cashier') {
                        return [
                            'success' => true,
                            'message' => 'Pembayaran kasir sudah dibuat sebelumnya.',
                            'payment' => $existingPayment,
                            'is_existing' => true,
                        ];
                    }

                    return $this->convertToCashier($order, $existingPayment);
                }

                // Payment pending: kembalikan yang sudah ada, KECUALI kasir
                // meminta channel berbeda (qris↔va) → charge baru.
                // Payment yang belum pernah di-charge juga boleh di-charge sekarang.
                $expectedType = match ($channel) {
                    'va' => 'bank_transfer',
                    'qris' => 'qris',
                    default => null,
                };
                $hasOnlineData = $existingPayment->snap_token
                    || $existingPayment->transaction_id
                    || $existingPayment->va_number;
                $typeMismatch = $expectedType && $existingPayment->payment_type
                    && $existingPayment->payment_type !== $expectedType;
                $bankMismatch = $channel === 'va' && $bank && $existingPayment->va_bank
                    && $existingPayment->va_bank !== $bank;

                // Edge case: payment lama hanya punya Snap token (belum punya
                // qr_data/va_number). Paksa charge ulang agar halaman pembayaran
                // menerima QR/VA yang bisa langsung ditampilkan.
                $missingChannelData = ($channel === 'qris' && empty($existingPayment->qr_data))
                    || ($channel === 'va' && (empty($existingPayment->va_number) || ($bank && $existingPayment->va_bank !== $bank)));

                if ($channel && $this->midtransService->isEnabled()
                    && (!$hasOnlineData || $typeMismatch || $bankMismatch || $missingChannelData)) {
                    return $this->chargeExisting($order, $existingPayment, $channel, $bank);
                }

                return [
                    'success' => true,
                    'message' => 'Pembayaran sudah dibuat sebelumnya.',
                    'payment' => $existingPayment,
                    'is_existing' => true,
                ];
            }
        }

        // Pembayaran kasir: pending lokal, dikonfirmasi admin dari dashboard.
        if ($method === 'cashier') {
            return $this->createCashierPayment($order);
        }

        // Generate data pembayaran demo
        $paymentCode = strtoupper(str_replace('-', '', $order->order_number));
        $qrData = 'DEMO-QRIS-' . $paymentCode . '-' . $order->total;
        $expiryMinutes = config('mejaku.payment.expiry_minutes');

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'payment_code' => $paymentCode,
            'qr_data' => $qrData,
            'amount' => $order->total,
            'status' => 'pending',
            'expires_at' => Carbon::now()->addMinutes($expiryMinutes),
        ]);

        $order->update(['payment_status' => 'pending']);

        // Bila Midtrans aktif:
        // - channel qris/va → charge langsung (QR / nomor VA tampil di modal).
        // - tanpa channel → Snap (semua metode tampil di popup).
        if ($this->midtransService->isEnabled()) {
            if (in_array($channel, ['qris', 'va'], true)) {
                return $this->chargeExisting($order, $payment, $channel, $bank);
            }

            $enabled = match ($channel) {
                'qris' => ['qris'],
                'va' => ['bank_transfer'],
                default => null,
            };
            $snap = $this->midtransService->createSnapTransaction($order, $payment, $enabled);
            if (!$snap['success']) {
                return [
                    'success' => false,
                    'message' => $snap['message'],
                ];
            }
            $payment->refresh();

            return [
                'success' => true,
                'message' => 'Pembayaran Midtrans berhasil dibuat.',
                'payment' => $payment,
                'is_existing' => false,
            ];
        }

        return [
            'success' => true,
            'message' => 'Pembayaran berhasil dibuat. [MODE DEMO]',
            'payment' => $payment,
            'is_existing' => false,
        ];
    }

    /**
     * Ubah payment pending yang sudah ada menjadi pembayaran kasir.
     */
    protected function convertToCashier(Order $order, Payment $payment): array
    {
        $payment->update([
            'method' => 'cashier',
            'payment_type' => 'cashier',
            'qr_data' => null,
            'va_number' => null,
            'va_bank' => null,
            'snap_token' => null,
            'redirect_url' => null,
            'midtrans_order_id' => null,
            'transaction_id' => null,
            'expires_at' => null,
            'status' => 'pending',
            'paid_at' => null,
        ]);

        $order->update(['payment_status' => 'pending']);

        return [
            'success' => true,
            'message' => 'Pembayaran kasir berhasil dibuat.',
            'payment' => $payment->fresh(),
            'is_existing' => false,
        ];
    }

    /**
     * Buat pembayaran kasir baru (tanpa Midtrans).
     */
    protected function createCashierPayment(Order $order): array
    {
        $paymentCode = strtoupper(str_replace('-', '', $order->order_number));

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'cashier',
            'payment_code' => $paymentCode,
            'amount' => $order->total,
            'status' => 'pending',
            'payment_type' => 'cashier',
            'expires_at' => null,
        ]);

        $order->update(['payment_status' => 'pending']);

        return [
            'success' => true,
            'message' => 'Pembayaran kasir berhasil dibuat. Silakan bayar di kasir.',
            'payment' => $payment,
            'is_existing' => false,
        ];
    }

    /**
     * Charge payment (baru maupun pending yang belum di-charge) via Core API.
     *
     * @return array{success: bool, message: string, payment?: Payment}
     */
    protected function chargeExisting(Order $order, Payment $payment, ?string $channel, ?string $bank): array
    {
        $targetMethod = $channel === 'va' ? 'bank_transfer' : 'qris';

        $charged = $this->midtransService->chargeDirect($order, $payment, $channel ?? 'qris', $bank);
        if (!$charged['success']) {
            // Jangan ubah method bila charge gagal — hindari state campur
            // (mis: method=qris tapi payment_type=cashier tanpa data charge).
            return [
                'success' => false,
                'message' => $charged['message'],
            ];
        }

        // Method & expiry hanya diperbarui setelah charge terbukti sukses.
        $payment->update([
            'method' => $targetMethod,
            'expires_at' => Carbon::now()->addMinutes(config('mejaku.payment.expiry_minutes')),
        ]);
        $payment->refresh();

        return [
            'success' => true,
            'message' => 'Tagihan Midtrans berhasil dibuat.',
            'payment' => $payment,
            'is_existing' => false,
        ];
    }

    /**
     * Sinkronkan payment pending yang memakai Midtrans tapi updated_at-nya
     * sudah basi (agar polling dashboard tidak menghantam API Midtrans
     * setiap request). Maksimal 20 payment per panggilan.
     */
    public function syncStalePending(int $staleSeconds = 60): void
    {
        if (!$this->midtransService->isEnabled()) {
            return;
        }

        $payments = Payment::with('order')
            ->where('status', 'pending')
            ->whereNotNull('midtrans_order_id')
            ->where('updated_at', '<', Carbon::now()->subSeconds($staleSeconds))
            ->limit(20)
            ->get();

        foreach ($payments as $payment) {
            if ($payment->order) {
                $this->midtransService->syncFromRemote($payment->order, $payment);
            }
        }
    }

    /**
     * Sinkron paksa satu order ke Midtrans (untuk endpoint detail / tombol
     * "Cek Pembayaran" — pelanggan baru bayar dan butuh status terbaru).
     * Tanpa $force, dilewati bila payment baru saja disinkron
     * (agar polling tiap 5 detik tidak menghantam API Midtrans).
     */
    public function syncOrderNow(Order $order, bool $force = false, int $staleSeconds = 30): void
    {
        if (!$this->midtransService->isEnabled()) {
            return;
        }

        $payment = Payment::where('order_id', $order->id)->latest()->first();

        if (!$payment || $payment->status !== 'pending' || !$payment->midtrans_order_id) {
            return;
        }

        if (!$force && $payment->updated_at && $payment->updated_at->gt(Carbon::now()->subSeconds($staleSeconds))) {
            return;
        }

        $this->midtransService->syncFromRemote($order, $payment);
    }
    public function checkStatus(Order $order): ?Payment
    {
        $payment = Payment::where('order_id', $order->id)
            ->latest()
            ->first();

        if (!$payment) {
            return null;
        }

        if ($payment->midtrans_order_id && $payment->status === 'pending') {
            $payment = $this->midtransService->syncFromRemote($order, $payment);
        }

        // Auto-expire jika sudah lewat waktu
        if ($payment->status === 'pending' && $payment->isExpired()) {
            $payment->update(['status' => 'expired']);
            $order->update(['payment_status' => 'expired']);
            $payment->refresh();
        }

        return $payment;
    }

    /**
     * Simulasi pembayaran (khusus demo mode).
     *
     * @return array{success: bool, message: string}
     */
    public function simulate(Order $order, Payment $payment, string $action): array
    {
        if ($payment->isPaid()) {
            return [
                'success' => false,
                'message' => 'Pembayaran sudah lunas.',
            ];
        }

        switch ($action) {
            case 'pay':
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => Carbon::now(),
                ]);
                $order->update([
                    'payment_status' => 'paid',
                    'preparation_status' => 'preparing',
                ]);
                return ['success' => true, 'message' => 'Simulasi: Pembayaran berhasil.'];

            case 'fail':
                $payment->update(['status' => 'failed']);
                $order->update(['payment_status' => 'failed']);
                return ['success' => true, 'message' => 'Simulasi: Pembayaran gagal.'];

            case 'expire':
                $payment->update(['status' => 'expired']);
                $order->update(['payment_status' => 'expired']);
                return ['success' => true, 'message' => 'Simulasi: Pembayaran kedaluwarsa.'];

            default:
                return ['success' => false, 'message' => 'Aksi tidak valid.'];
        }
    }

    /**
     * Format payment untuk response JSON.
     */
    public function formatPayment(Payment $payment, Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'method' => $payment->method,
            'method_label' => $this->getMethodLabel($payment->method),
            'payment_code' => $payment->payment_code,
            'qr_data' => $payment->qr_data,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'expires_at' => $payment->expires_at?->toISOString(),
            'paid_at' => $payment->paid_at?->toISOString(),
            'payment_status' => $order->payment_status,
            'preparation_status' => $order->preparation_status,
            'is_midtrans' => $payment->snap_token !== null || $payment->transaction_id !== null,
            'snap_token' => $payment->snap_token,
            'redirect_url' => $payment->redirect_url,
            'midtrans_order_id' => $payment->midtrans_order_id,
            'payment_type' => $payment->payment_type,
            'va_number' => $payment->va_number,
            'va_bank' => $payment->va_bank,
            'midtrans_client_key' => config('midtrans.client_key'),
            'midtrans_is_production' => (bool) config('midtrans.is_production', false),
        ];
    }

    /**
     * Label metode pembayaran.
     */
    public function getMethodLabel(string $method): string
    {
        return match ($method) {
            'qris' => 'QRIS',
            'bank_transfer' => 'Transfer Bank / VA',
            'cashier' => 'Kasir',
            'e_wallet' => 'Dompet Digital',
            'midtrans' => 'Midtrans',
            default => $method,
        };
    }
}
