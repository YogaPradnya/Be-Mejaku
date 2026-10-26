<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderRequest;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService,
    ) {}

    /**
     * POST /api/orders
     * Buat pesanan baru dengan kalkulasi harga server-side.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Cek idempotency key untuk mencegah order ganda
        $existing = $this->orderService->findByIdempotencyKey($validated['idempotency_key'] ?? null);
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Pesanan sudah dibuat sebelumnya.',
                'data' => $this->orderService->formatOrder($existing),
            ], 200);
        }

        try {
            $order = $this->orderService->createOrder($validated);
            $order->load(['items.menuItem', 'items.portion', 'items.spiceLevel', 'items.addons', 'table']);

            app(\App\Services\NoSqlSyncService::class)->recordOrderAudit($order->order_number, 'order_created', [
                'customer_name' => $order->notes,
                'total_amount' => $order->total,
                'items_count' => $order->items->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat.',
                'data' => $this->orderService->formatOrder($order),
                'demo_mode' => config('mejaku.demo_mode'),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Data pesanan tidak valid.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pesanan. Silakan coba lagi.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/orders/{orderNumber}
     * Ambil detail pesanan (dipakai polling halaman customer).
     */
    public function show(string $orderNumber): JsonResponse
    {
        $order = $this->orderService->getOrderByNumber($orderNumber);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        // Tarik status terbaru dari Midtrans agar halaman customer
        // yang baru bayar langsung melihat status Lunas.
        // ?fresh=1 memaksa sinkron (dipakai tombol "Cek Pembayaran");
        // polling rutin tetap memakai throttle agar tidak menghantam Midtrans.
        $this->paymentService->syncOrderNow($order, request()->boolean('fresh'));
        $order->refresh();

        return response()->json([
            'success' => true,
            'data' => $this->orderService->formatOrder($order),
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }

    /**
     * PATCH /api/orders/{orderNumber}/status
     * Update status penyiapan pesanan (untuk demo).
     */
    public function updateStatus(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate([
            'preparation_status' => 'required|string|in:received,preparing,ready,completed',
        ]);

        $order = $this->orderService->getOrderByNumber($orderNumber);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        $order = $this->orderService->updatePreparationStatus($order, $request->preparation_status);
        app(\App\Services\NoSqlSyncService::class)->recordOrderAudit($order->order_number, 'prep_status_updated', [
            'preparation_status' => $order->preparation_status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status pesanan diperbarui.',
            'data' => [
                'order_number' => $order->order_number,
                'preparation_status' => $order->preparation_status,
                'payment_status' => $order->payment_status,
            ],
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }
}
