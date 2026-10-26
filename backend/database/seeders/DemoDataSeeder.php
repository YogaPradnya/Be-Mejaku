<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed data demo sesuai PRD MejaKu.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // =============================================
        // 1. Merchant: Warung Bali Dwi
        // =============================================
        $merchantId = DB::table('merchants')->insertGetId([
            'name' => 'Warung Bali Dwi',
            'description' => 'Warung masakan Bali autentik dengan cita rasa khas Pulau Dewata. Menyajikan berbagai hidangan tradisional Bali yang dimasak dengan bumbu rempah pilihan.',
            'address' => 'Jakarta Culinary Hall, Pujasera Utama Blok B',
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // =============================================
        // 2. Menu Item: Nasi Campur Bali Agung
        // =============================================
        $menuItemId = DB::table('menu_items')->insertGetId([
            'merchant_id' => $merchantId,
            'name' => 'Nasi Campur Bali Agung',
            'slug' => 'nasi-campur-bali-agung',
            'description' => 'Nasi campur khas Bali dengan lauk lengkap: ayam betutu suwir, sate lilit, lawar merah, sambal matah, dan kerupuk kulit. Disajikan dengan nasi putih hangat dan sayur urap segar.',
            'base_price' => 58000,
            'stock' => 50,
            'rating' => 4.8,
            'rating_count' => 256,
            'image' => null,
            'is_available' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // =============================================
        // 3. Porsi
        // =============================================
        DB::table('menu_portions')->insert([
            [
                'menu_item_id' => $menuItemId,
                'name' => 'Biasa',
                'surcharge' => 0,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'menu_item_id' => $menuItemId,
                'name' => 'Jumbo',
                'surcharge' => 15000,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // =============================================
        // 4. Addons (Pelengkap)
        // =============================================
        DB::table('menu_addons')->insert([
            [
                'menu_item_id' => $menuItemId,
                'name' => 'Ekstra Sambal Matah',
                'price' => 5000,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'menu_item_id' => $menuItemId,
                'name' => 'Sate Lilit Tuna',
                'price' => 8000,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'menu_item_id' => $menuItemId,
                'name' => 'Kulit Ayam Bali Renyah',
                'price' => 12000,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // =============================================
        // 5. Spice Levels (Tingkat Pedas)
        // =============================================
        DB::table('spice_levels')->insert([
            [
                'name' => 'Tidak Pedas',
                'level' => 0,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Sedang',
                'level' => 1,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Pedas',
                'level' => 2,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Sangat Pedas',
                'level' => 3,
                'sort_order' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // =============================================
        // 6. Meja: A12
        // =============================================
        DB::table('tables')->insert([
            [
                'code' => 'A12',
                'floor' => 'Lantai 2',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'B05',
                'floor' => 'Lantai 1',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'C08',
                'floor' => 'Lantai 3',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // =============================================
        // 7. Promo Codes (aktif untuk checkout)
        // =============================================
        DB::table('promo_codes')->insert([
            [
                'code' => 'MEJAKU10',
                'type' => 'percentage',
                'value' => 10,
                'min_order' => 50000,
                'max_discount' => 15000,
                'is_active' => true,
                'valid_from' => $now,
                'valid_until' => $now->copy()->addMonths(3),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'HEMAT5K',
                'type' => 'fixed',
                'value' => 5000,
                'min_order' => 30000,
                'max_discount' => null,
                'is_active' => true,
                'valid_from' => $now,
                'valid_until' => $now->copy()->addMonths(3),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
