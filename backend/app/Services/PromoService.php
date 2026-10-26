<?php

namespace App\Services;

use App\Models\PromoCode;

class PromoService
{
    /**
     * Validasi kode promo dan hitung diskon.
     *
     * @return array{valid: bool, message: string, discount?: int, promo?: array}
     */
    public function validateAndCalculate(string $code, int $subtotal): array
    {
        $code = strtoupper(trim($code));
        $promo = PromoCode::where('code', $code)->first();

        if (!$promo) {
            return [
                'valid' => false,
                'message' => 'Kode promo tidak ditemukan.',
                'discount' => 0,
            ];
        }

        // Sesuai PRD: untuk demo tanpa aturan promo, berikan feedback bahwa promo belum tersedia
        if (!$promo->is_active) {
            return [
                'valid' => false,
                'message' => 'Promo belum tersedia saat ini.',
                'discount' => 0,
            ];
        }

        if (!$promo->isValid()) {
            return [
                'valid' => false,
                'message' => 'Kode promo sudah tidak berlaku.',
                'discount' => 0,
            ];
        }

        if ($subtotal < $promo->min_order) {
            return [
                'valid' => false,
                'message' => 'Minimal pembelian Rp ' . number_format($promo->min_order, 0, ',', '.') . ' untuk menggunakan promo ini.',
                'discount' => 0,
            ];
        }

        $discount = $promo->calculateDiscount($subtotal);

        return [
            'valid' => true,
            'message' => 'Promo berhasil diterapkan.',
            'discount' => $discount,
            'promo' => [
                'code' => $promo->code,
                'type' => $promo->type,
                'value' => $promo->value,
                'description' => $this->getDescription($promo),
            ],
        ];
    }

    /**
     * Daftar promo aktif yang boleh ditampilkan di halaman checkout.
     *
     * @return array<int, array{code: string, type: string, value: int, min_order: int, max_discount: int|null, description: string}>
     */
    public function activePromos(): array
    {
        return PromoCode::where('is_active', true)
            ->orderBy('min_order')
            ->orderBy('code')
            ->get()
            ->filter(fn (PromoCode $promo) => $promo->isValid())
            ->map(fn (PromoCode $promo) => [
                'code' => $promo->code,
                'type' => $promo->type,
                'value' => $promo->value,
                'min_order' => $promo->min_order,
                'max_discount' => $promo->max_discount,
                'description' => $this->getDescription($promo),
            ])
            ->values()
            ->all();
    }

    /**
     * Deskripsi promo yang mudah dibaca.
     */
    public function getDescription(PromoCode $promo): string
    {
        if ($promo->type === 'percentage') {
            $desc = 'Diskon ' . $promo->value . '%';
            if ($promo->max_discount) {
                $desc .= ' (maks. Rp ' . number_format($promo->max_discount, 0, ',', '.') . ')';
            }
            return $desc;
        }

        return 'Potongan Rp ' . number_format($promo->value, 0, ',', '.');
    }
}
