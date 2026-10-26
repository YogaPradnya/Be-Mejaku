<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentService $paymentService,
    ) {}

    /**
     * GET /api/admin/orders
     * Daftar pesanan untuk dashboard dapur/kasir dengan filter + pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preparation_status' => 'sometimes|string|in:received,preparing,ready,completed',
            'payment_status' => 'sometimes|string|in:pending,checking,paid,failed,expired,cancelled',
            'search' => 'sometimes|string|max:100',
            'table_code' => 'sometimes|string|max:20',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort' => 'sometimes|string|in:latest,oldest',
        ]);

        $query = Order::with([
            'items.menuItem:id,name,image',
            'items.portion',
            'items.spiceLevel',
            'items.addons',
            'table:id,code,floor',
            'payment',
        ]);

        if (!empty($validated['preparation_status'])) {
            $query->where('preparation_status', $validated['preparation_status']);
        }

        if (!empty($validated['payment_status'])) {
            $query->where('payment_status', $validated['payment_status']);
        }

        if (!empty($validated['table_code'])) {
            $query->whereHas('table', fn ($q) => $q->where('code', $validated['table_code']));
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('promo_code', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $query->orderBy('created_at', ($validated['sort'] ?? 'latest') === 'oldest' ? 'asc' : 'desc');

        // Tarik status terbaru dari Midtrans untuk payment pending yang basi,
        // agar daftar dashboard ikut terupdate tanpa perlu buka detail.
        $this->paymentService->syncStalePending();

        $perPage = $validated['per_page'] ?? 15;
        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(function (Order $order) {
            $formatted = $this->orderService->formatOrder($order);
            $formatted['item_count'] = $order->items->sum('quantity');
            return $formatted;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }

    /**
     * GET /api/admin/orders/{orderNumber}
     * Detail satu pesanan untuk dashboard (reuse formatOrder).
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->orderService->getOrderByNumber($orderNumber);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        $this->paymentService->syncOrderNow($order, $request->boolean('fresh'));
        $order->refresh();

        $data = $this->orderService->formatOrder($order);
        $data['item_count'] = $order->items->sum('quantity');

        return response()->json([
            'success' => true,
            'data' => $data,
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }

    /**
     * PATCH /api/admin/orders/{orderNumber}/preparation
     * Update status dapur dari dashboard.
     */
    public function updatePreparation(Request $request, string $orderNumber): JsonResponse
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

        return response()->json([
            'success' => true,
            'message' => 'Status penyiapan diperbarui.',
            'data' => [
                'order_number' => $order->order_number,
                'preparation_status' => $order->preparation_status,
                'payment_status' => $order->payment_status,
            ],
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }

    /**
     * PATCH /api/admin/orders/{orderNumber}/payment
     * Update status bayar manual dari kasir (tunai/koreksi).
     * Sinkron ke record payment terakhir bila ada.
     */
    public function updatePayment(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate([
            'payment_status' => 'required|string|in:pending,checking,paid,failed,expired,cancelled',
        ]);

        $order = $this->orderService->getOrderByNumber($orderNumber);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        $status = $request->payment_status;
        $order->update(['payment_status' => $status]);

        $payment = Payment::where('order_id', $order->id)->latest()->first();
        if (!$payment) {
            // Tandai-lunas manual tanpa record payment: buatkan agar
            // kolom metode & riwayat kasir tidak kosong.
            $payment = Payment::create([
                'order_id' => $order->id,
                'method' => 'cashier',
                'payment_code' => strtoupper(str_replace('-', '', $order->order_number)),
                'amount' => $order->total,
                'status' => 'pending',
                'payment_type' => 'cashier',
            ]);
        }
        if ($payment && in_array($payment->status, ['pending', 'failed', 'expired', 'paid'])) {
            $payment->update([
                'status' => $status === 'paid' ? 'paid' : $status,
                'paid_at' => $status === 'paid' ? now() : $payment->paid_at,
            ]);
        }

        if ($status === 'paid' && $order->preparation_status === 'received') {
            $order->update(['preparation_status' => 'preparing']);
        }

        $order->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Status pembayaran diperbarui.',
            'data' => [
                'order_number' => $order->order_number,
                'preparation_status' => $order->preparation_status,
                'payment_status' => $order->payment_status,
            ],
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }

    /**
     * POST /api/admin/orders/{orderNumber}/cancel
     * Batalkan pesanan yang belum lunas + kembalikan stok.
     */
    public function cancel(string $orderNumber): JsonResponse
    {
        $order = $this->orderService->getOrderByNumber($orderNumber);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan.',
            ], 404);
        }

        try {
            $order = $this->orderService->cancelOrder($order);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Gagal membatalkan pesanan.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan dibatalkan, stok dikembalikan.',
            'data' => [
                'order_number' => $order->order_number,
                'preparation_status' => $order->preparation_status,
                'payment_status' => $order->payment_status,
            ],
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }
}
