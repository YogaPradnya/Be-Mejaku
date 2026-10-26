<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePaymentRequest;
use App\Models\Order;
use App\Services\MidtransService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected MidtransService $midtransService,
    ) {}

    /**
     * POST /api/payments/{orderNumber}
     * Buat transaksi pembayaran (generate QR/code) - Mode Demo.
     */
    public function store(CreatePaymentRequest $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validated();
        $result = $this->paymentService->createPayment($order, $validated['method'], $validated['channel'] ?? null, $validated['bank'] ?? null);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        $statusCode = ($result['is_existing'] ?? false) ? 200 : 201;

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $this->paymentService->formatPayment($result['payment'], $order->fresh()),
            'demo_mode' => config('mejaku.demo_mode'),
        ], $statusCode);
    }

    /**
     * GET /api/payments/{orderNumber}/status
     * Cek status pembayaran.
     */
    public function status(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        $payment = $this->paymentService->checkStatus($order);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran belum dibuat untuk pesanan ini.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->paymentService->formatPayment($payment, $order->fresh()),
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }

    /**
     * POST /api/payments/{orderNumber}/simulate
     * Simulasi pembayaran (khusus demo mode).
     */
    public function simulate(Request $request, string $orderNumber): JsonResponse
    {
        if (!config('mejaku.demo_mode')) {
            return response()->json([
                'success' => false,
                'message' => 'Simulasi hanya tersedia dalam mode demo.',
            ], 403);
        }

        $request->validate([
            'action' => 'required|string|in:pay,fail,expire',
        ]);

        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        $payment = $this->paymentService->checkStatus($order);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Pembayaran belum dibuat.',
            ], 404);
        }

        $result = $this->paymentService->simulate($order, $payment, $request->action);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $this->paymentService->formatPayment($payment->fresh(), $order->fresh()),
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }

    /**
     * POST /api/midtrans/notification
     * Webhook server-to-server dari Midtrans (tanpa auth, verifikasi signature).
     * Daftarkan URL ini di dashboard Midtrans (Settings > Configuration).
     * Catatan: Midtrans tidak bisa menjangkau localhost — gunakan ngrok
     * atau URL publik saat uji coba sandbox.
     */
    public function notification(Request $request): JsonResponse
    {
        $result = $this->midtransService->handleNotification($request->all());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    /**
     * GET /api/payments/finish
     * Landing page setelah pelanggan menyelesaikan popup Snap.
     * Status asli tetap dikonfirmasi via webhook / tombol "Cek Pembayaran".
     */
    public function finish(Request $request)
    {
        $orderId = (string) $request->query('order_id', '');

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran diproses. Status final dikonfirmasi via notifikasi Midtrans.',
            'order_id' => $orderId,
        ]);
    }
}
