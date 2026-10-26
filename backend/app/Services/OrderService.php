<?php

namespace App\Services;

use App\Models\Order;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    protected PricingService $pricingService;
    protected PromoService $promoService;

    public function __construct(PricingService $pricingService, PromoService $promoService)
    {
        $this->pricingService = $pricingService;
        $this->promoService = $promoService;
    }

    /**
     * Cek idempotency key. Kembalikan order jika sudah ada.
     */
    public function findByIdempotencyKey(?string $key): ?Order
    {
        if (empty($key)) {
            return null;
        }

        return Order::where('idempotency_key', $key)->first();
    }

    /**
     * Buat pesanan baru dengan kalkulasi harga server-side.
     *
     * @param array $data Data yang sudah divalidasi dari CreateOrderRequest
     * @return Order
     * @throws \Exception
     */
    public function createOrder(array $data): Order
    {
        $table = Table::where('code', $data['table_code'])->firstOrFail();

        return DB::transaction(function () use ($data, $table) {
            $subtotal = 0;
            $orderItems = [];

            // Kalkulasi harga setiap item (authoritative, server-side)
            foreach ($data['items'] as $itemData) {
                // Kunci baris agar dua pesanan bersamaan tidak oversell.
                $menuItem = MenuItem::where('id', $itemData['menu_item_id'])->lockForUpdate()->first();

                if (!$menuItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Menu tidak ditemukan.',
                    ]);
                }

                $quantity = $itemData['quantity'];

                if (!$menuItem->is_available) {
                    throw ValidationException::withMessages([
                        'items' => "Menu \"{$menuItem->name}\" sedang nonaktif.",
                    ]);
                }

                if ($menuItem->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Stok \"{$menuItem->name}\" tidak cukup (sisa {$menuItem->stock}).",
                    ]);
                }

                $portionId = $itemData['portion_id'] ?? null;
                $addonIds = $itemData['addon_ids'] ?? [];

                // Gunakan PricingService untuk kalkulasi
                $pricing = $this->pricingService->calculateUnitPrice($menuItem, $portionId, $addonIds);
                $lineTotal = $this->pricingService->calculateLineTotal($pricing['unit_price'], $quantity);
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'menu_item_id' => $menuItem->id,
                    'menu_portion_id' => $portionId,
                    'spice_level_id' => $itemData['spice_level_id'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $pricing['unit_price'],
                    'line_total' => $lineTotal,
                    'addons' => $pricing['addons_detail'],
                ];
            }

            // Hitung diskon dari promo
            $discount = 0;
            $promoCode = $data['promo_code'] ?? null;
            if ($promoCode) {
                $promoResult = $this->promoService->validateAndCalculate($promoCode, $subtotal);
                if ($promoResult['valid']) {
                    $discount = $promoResult['discount'];
                }
            }

            // Hitung ringkasan biaya via PricingService
            $summary = $this->pricingService->calculateOrderSummary($subtotal, $discount);

            // Generate order number
            $orderNumber = $this->generateOrderNumber($summary['total']);

            // Buat order
            $order = Order::create([
                'order_number' => $orderNumber,
                'table_id' => $table->id,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $summary['subtotal'],
                'service_fee' => $summary['service_fee'],
                'tax' => $summary['tax'],
                'discount' => $summary['discount'],
                'total' => $summary['total'],
                'promo_code' => $promoCode,
                'delivery_type' => $data['delivery_type'] ?? 'dine_in',
                'payment_status' => 'pending',
                'preparation_status' => 'received',
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            // Kurangi stok dalam transaksi yang sama (aman dari race).
            foreach ($orderItems as $orderItem) {
                MenuItem::where('id', $orderItem['menu_item_id'])
                    ->decrement('stock', $orderItem['quantity']);
            }

            // Buat order items dan addons
            foreach ($orderItems as $itemData) {
                $addonsData = $itemData['addons'];
                unset($itemData['addons']);

                $orderItem = $order->items()->create($itemData);

                foreach ($addonsData as $addon) {
                    $orderItem->addons()->create($addon);
                }
            }

            return $order;
        });
    }

    /**
     * Ambil order berdasarkan nomor pesanan dengan relasi lengkap.
     */
    public function getOrderByNumber(string $orderNumber): ?Order
    {
        return Order::with([
            'items.menuItem',
            'items.portion',
            'items.spiceLevel',
            'items.addons',
            'table',
            'payment',
        ])->where('order_number', $orderNumber)->first();
    }

    /**
     * Batalkan pesanan: kembalikan stok, tandai payment cancelled.
     * Pesanan yang sudah lunas tidak bisa dibatalkan dari sini.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function cancelOrder(Order $order): Order
    {
        if ($order->payment_status === 'paid') {
            throw ValidationException::withMessages([
                'order' => 'Pesanan yang sudah lunas tidak bisa dibatalkan.',
            ]);
        }

        if ($order->payment_status === 'cancelled') {
            return $order;
        }

        return DB::transaction(function () use ($order) {
            $order->loadMissing('items');

            foreach ($order->items as $item) {
                MenuItem::where('id', $item->menu_item_id)->increment('stock', $item->quantity);
            }

            $order->update(['payment_status' => 'cancelled']);

            Payment::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'failed', 'expired'])
                ->update(['status' => 'cancelled']);

            return $order->fresh();
        });
    }

    /**
     * Update status penyiapan pesanan.
     */
    public function updatePreparationStatus(Order $order, string $status): Order
    {
        $order->update(['preparation_status' => $status]);
        return $order->fresh();
    }

    /**
     * Generate nomor pesanan unik.
     */
    private function generateOrderNumber(int $total): string
    {
        $suffix = strtoupper(Str::random(2));
        $number = 'MJK-' . $total . '-' . $suffix;

        // Pastikan unik
        while (Order::where('order_number', $number)->exists()) {
            $suffix = strtoupper(Str::random(2));
            $number = 'MJK-' . $total . '-' . $suffix;
        }

        return $number;
    }

    /**
     * Format order untuk response JSON.
     */
    public function formatOrder(Order $order): array
    {
        $order->loadMissing([
            'items.menuItem',
            'items.portion',
            'items.spiceLevel',
            'items.addons',
            'table',
            'payment',
        ]);

        $data = [
            'order_number' => $order->order_number,
            'table' => $order->table ? [
                'code' => $order->table->code,
                'floor' => $order->table->floor,
            ] : null,
            'delivery_type' => $order->delivery_type,
            'notes' => $order->notes,
            'items' => $order->items->map(fn($item) => [
                'menu_item' => [
                    'id' => $item->menuItem->id,
                    'name' => $item->menuItem->name,
                    'image' => $item->menuItem->image,
                ],
                'portion' => $item->portion ? [
                    'name' => $item->portion->name,
                    'surcharge' => $item->portion->surcharge,
                ] : null,
                'spice_level' => $item->spiceLevel ? [
                    'name' => $item->spiceLevel->name,
                    'level' => $item->spiceLevel->level,
                ] : null,
                'addons' => $item->addons->map(fn($a) => [
                    'name' => $a->addon_name,
                    'price' => $a->addon_price,
                ]),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ]),
            'subtotal' => $order->subtotal,
            'service_fee' => $order->service_fee,
            'tax' => $order->tax,
            'discount' => $order->discount,
            'total' => $order->total,
            'promo_code' => $order->promo_code,
            'payment_status' => $order->payment_status,
            'preparation_status' => $order->preparation_status,
            'created_at' => $order->created_at->toISOString(),
        ];

        if ($order->payment) {
            $data['payment'] = [
                'method' => $order->payment->method,
                'method_label' => match ($order->payment->method) {
                    'qris' => 'QRIS',
                    'bank_transfer' => 'Transfer Bank / VA',
                    'cashier' => 'Kasir',
                    'e_wallet' => 'Dompet Digital',
                    'midtrans' => 'Midtrans',
                    default => $order->payment->method,
                },
                'payment_code' => $order->payment->payment_code,
                'amount' => $order->payment->amount,
                'status' => $order->payment->status,
                'payment_type' => $order->payment->payment_type,
                'va_number' => $order->payment->va_number,
                'va_bank' => $order->payment->va_bank,
                'expires_at' => $order->payment->expires_at?->toISOString(),
                'paid_at' => $order->payment->paid_at?->toISOString(),
            ];
        }

        return $data;
    }
}
