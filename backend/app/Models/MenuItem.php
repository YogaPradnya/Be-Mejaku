<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'merchant_id',
        'name',
        'slug',
        'description',
        'base_price',
        'stock',
        'has_spice_option',
        'rating',
        'rating_count',
        'image',
        'is_available',
    ];

    protected $casts = [
        'base_price' => 'integer',
        'stock' => 'integer',
        'has_spice_option' => 'boolean',
        'rating' => 'decimal:1',
        'rating_count' => 'integer',
        'is_available' => 'boolean',
    ];

    /**
     * Merchant pemilik menu ini.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Pilihan porsi untuk menu ini.
     */
    public function portions(): HasMany
    {
        return $this->hasMany(MenuPortion::class)->orderBy('sort_order');
    }

    /**
     * Pelengkap (addon) untuk menu ini.
     */
    public function addons(): HasMany
    {
        return $this->hasMany(MenuAddon::class)->orderBy('sort_order');
    }

    /**
     * Ulasan dan rating untuk menu ini.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(MenuRating::class)->latest();
    }

    /**
     * Hitung ulang dan simpan rata-rata rating serta jumlah ulasan.
     */
    public function refreshRatingStats(): void
    {
        $this->rating_count = $this->ratings()->count();
        $this->rating = round((float) ($this->ratings()->avg('rating') ?: 0), 1);
        $this->save();
    }
}

