<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/stats
     * Ringkasan metrik dashboard (kasir & dapur).
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'sometimes|date_format:Y-m-d',
            'merchant_id' => 'sometimes|integer|exists:merchants,id',
        ]);

        $date = isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'])
            : Carbon::today();
        $merchantId = $validated['merchant_id'] ?? null;

        $dayOrders = Order::whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->when($merchantId, fn ($q) => $q->whereHas('items.menuItem', fn ($qq) => $qq->where('merchant_id', $merchantId)));

        $paidOrders = (clone $dayOrders)->where('payment_status', 'paid');

        $revenueToday = (int) (clone $paidOrders)->sum('total');
        $totalOrdersToday = (int) (clone $dayOrders)->count();

        $soldItemsToday = (int) OrderItem::whereHas('order', function ($q) use ($date, $merchantId) {
            $q->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                ->where('payment_status', 'paid');
        })->when($merchantId, fn ($q) => $q->whereHas('menuItem', fn ($qq) => $qq->where('merchant_id', $merchantId)))
            ->sum('quantity');

        $kitchenCounts = (clone $dayOrders)
            ->selectRaw('preparation_status, COUNT(*) as count')
            ->groupBy('preparation_status')
            ->pluck('count', 'preparation_status');

        $pendingPayments = (int) Order::where('payment_status', 'pending')
            ->when($merchantId, fn ($q) => $q->whereHas('items.menuItem', fn ($qq) => $qq->where('merchant_id', $merchantId)))
            ->count();

        $topItems = OrderItem::with('menuItem:id,name')
            ->whereHas('order', function ($q) use ($date, $merchantId) {
                $q->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                    ->where('payment_status', 'paid');
                if ($merchantId) {
                    $q->whereHas('items.menuItem', fn ($qq) => $qq->where('merchant_id', $merchantId));
                }
            })
            ->selectRaw('menu_item_id, SUM(quantity) as qty')
            ->groupBy('menu_item_id')
            ->orderByDesc('qty')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'menu_item_id' => $row->menu_item_id,
                'name' => $row->menuItem->name ?? '-',
                'qty' => (int) $row->qty,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date->toDateString(),
                'revenue_today' => $revenueToday,
                'revenue_paid_only' => true,
                'total_orders_today' => $totalOrdersToday,
                'sold_items_today' => $soldItemsToday,
                'active_kitchen' => [
                    'received' => (int) ($kitchenCounts['received'] ?? 0),
                    'preparing' => (int) ($kitchenCounts['preparing'] ?? 0),
                    'ready' => (int) ($kitchenCounts['ready'] ?? 0),
                ],
                'pending_payments' => $pendingPayments,
                'top_items' => $topItems,
            ],
            'demo_mode' => config('mejaku.demo_mode'),
        ]);
    }
}
