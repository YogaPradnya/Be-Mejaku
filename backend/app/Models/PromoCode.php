<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order',
        'max_discount',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'value' => 'integer',
        'min_order' => 'integer',
        'max_discount' => 'integer',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    /**
     * Cek apakah promo code valid untuk digunakan.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    /**
     * Hitung diskon berdasarkan subtotal.
     */
    public function calculateDiscount(int $subtotal): int
    {
        if ($subtotal < $this->min_order) {
            return 0;
        }

        if ($this->type === 'percentage') {
            $discount = (int) round($subtotal * ($this->value / 100));

            if ($this->max_discount !== null) {
                $discount = min($discount, $this->max_discount);
            }

            return $discount;
        }

        // fixed discount
        return min($this->value, $subtotal);
    }
}
