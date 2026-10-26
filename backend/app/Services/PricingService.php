<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\MenuPortion;
use App\Models\MenuAddon;

class PricingService
{
    /**
     * Hitung unit price untuk satu item berdasarkan konfigurasi.
     *
     * Formula PRD:
     * unitPrice = basePrice + portionSurcharge + sum(addonPrices)
     */
    public function calculateUnitPrice(MenuItem $menuItem, ?int $portionId = null, array $addonIds = []): array
    {
        $basePrice = $menuItem->base_price;
        $portionSurcharge = 0;
        $addonTotal = 0;
        $addonsDetail = [];

        // Hitung surcharge porsi
        if ($portionId) {
            $portion = MenuPortion::where('id', $portionId)
                ->where('menu_item_id', $menuItem->id)
                ->first();

            if ($portion) {
                $portionSurcharge = $portion->surcharge;
            }
        }

        // Hitung total addon
        if (!empty($addonIds)) {
            $addons = MenuAddon::whereIn('id', $addonIds)
                ->where('menu_item_id', $menuItem->id)
                ->get();

            foreach ($addons as $addon) {
                $addonTotal += $addon->price;
                $addonsDetail[] = [
                    'menu_addon_id' => $addon->id,
                    'addon_name' => $addon->name,
                    'addon_price' => $addon->price,
                ];
            }
        }

        $unitPrice = $basePrice + $portionSurcharge + $addonTotal;

        return [
            'base_price' => $basePrice,
            'portion_surcharge' => $portionSurcharge,
            'addon_total' => $addonTotal,
            'unit_price' => $unitPrice,
            'addons_detail' => $addonsDetail,
        ];
    }

    /**
     * Hitung line total (unit price * quantity).
     */
    public function calculateLineTotal(int $unitPrice, int $quantity): int
    {
        return $unitPrice * $quantity;
    }

    /**
     * Hitung ringkasan biaya pesanan.
     *
     * Formula PRD:
     * subtotal  = sum(unitPrice * quantity) untuk semua item
     * tax       = round((subtotal - discount + serviceFee) * taxRate)
     * total     = subtotal - discount + serviceFee + tax
     */
    public function calculateOrderSummary(int $subtotal, int $discount = 0): array
    {
        $serviceFee = config('mejaku.service_fee');
        $taxRate = config('mejaku.tax_rate');

        $taxableAmount = $subtotal - $discount + $serviceFee;
        $tax = (int) round($taxableAmount * $taxRate);
        $total = $subtotal - $discount + $serviceFee + $tax;

        return [
            'subtotal' => $subtotal,
            'service_fee' => $serviceFee,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    /**
     * Validasi bahwa kalkulasi harga sesuai dengan contoh PRD.
     *
     * Contoh referensi:
     * - Harga dasar: 58.000
     * - Ekstra sambal: 5.000
     * - Subtotal: 63.000
     * - Service fee: 3.000
     * - Pajak: 6.600
     * - Total: 72.600
     */
    public function validatePrdExample(): bool
    {
        $unitPrice = 58000 + 0 + 5000; // base + porsi biasa + ekstra sambal
        $subtotal = $unitPrice * 1; // quantity 1
        $summary = $this->calculateOrderSummary($subtotal);

        return $summary['subtotal'] === 63000
            && $summary['service_fee'] === 3000
            && $summary['tax'] === 6600
            && $summary['total'] === 72600;
    }
}
