<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * GET /api/admin/payments
     * Rekap kasir: join payments + orders + tables.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,checking,paid,failed,expired,cancelled',
            'method' => 'sometimes|string|in:qris,bank_transfer,e_wallet,midtrans,cashier',
            'date' => 'sometimes|date_format:Y-m-d',
            'search' => 'sometimes|string|max:100',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Payment::with(['order.table:id,code,floor'])
            ->orderByDesc('created_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['method'])) {
            $query->where('method', $validated['method']);
        }

        if (!empty($validated['date'])) {
            $query->whereDate('created_at', $validated['date']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('payment_code', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($qq) => $qq->where('order_number', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->paginate($validated['per_page'] ?? 15);

        $data = collect($paginator->items())
            ->filter(fn ($p) => $p->order !== null)
            ->map(fn ($p) => $this->paymentService->formatPayment($p, $p->order))
            ->values();

        $summaryQuery = clone $query;
        $paidTotal = (int) (clone $summaryQuery)->where('status', 'paid')->sum('amount');
        $paidCount = (int) (clone $summaryQuery)->where('status', 'paid')->count();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'summary' => [
                'paid_total' => $paidTotal,
                'paid_count' => $paidCount,
            ],
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }
}
