<?php

namespace Tests\Feature;

use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
        \Laravel\Sanctum\Sanctum::actingAs(\App\Models\User::factory()->create());
    }

    public function test_menu_crud_with_stock_portions_addons(): void
    {
        $merchant = Merchant::first();

        $create = $this->postJson('/api/admin/menu', [
            'merchant_id' => $merchant->id,
            'name' => 'Nasi Campur Bali Agung Spesial',
            'description' => 'Varian spesial',
            'base_price' => 58000,
            'stock' => 25,
            'portions' => [
                ['name' => 'Biasa', 'surcharge' => 0],
                ['name' => 'Jumbo', 'surcharge' => 15000],
            ],
            'addons' => [
                ['name' => 'Ekstra Sambal Matah', 'price' => 5000],
            ],
        ]);
        $create->assertStatus(201)->assertJson(['success' => true]);
        $id = $create->json('data.id');
        $this->assertEquals(25, $create->json('data.stock'));
        $this->assertCount(2, $create->json('data.portions'));
        $this->assertNotEmpty($create->json('data.slug'));

        // List + search
        $list = $this->getJson('/api/admin/menu?search=Spesial');
        $list->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $list->json('meta.total'));

        // Update: ganti harga + replace portions
        $update = $this->putJson("/api/admin/menu/{$id}", [
            'base_price' => 60000,
            'portions' => [['name' => 'Jumbo Saja', 'surcharge' => 12000]],
        ]);
        $update->assertStatus(200);
        $this->assertEquals(60000, $update->json('data.base_price'));
        $this->assertCount(1, $update->json('data.portions'));

        // Toggle -> nonaktif
        $toggle = $this->patchJson("/api/admin/menu/{$id}/toggle");
        $toggle->assertStatus(200);
        $this->assertFalse($toggle->json('data.is_available'));

        // Soft delete (default) -> is_available false, data masih ada
        $soft = $this->deleteJson("/api/admin/menu/{$id}");
        $soft->assertStatus(200);
        $this->getJson("/api/admin/menu/{$id}")->assertStatus(200);

        // Force delete -> hilang
        $this->deleteJson("/api/admin/menu/{$id}?force=1")->assertStatus(200);
        $this->getJson("/api/admin/menu/{$id}")->assertStatus(404);
    }

    public function test_payments_recap(): void
    {
        // Buat order + payment agar rekap tidak kosong
        $menuItem = \App\Models\MenuItem::first();
        $orderNumber = $this->postJson('/api/orders', [
            'table_code' => 'A12',
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 1]],
        ])->json('data.order_number');
        $this->postJson("/api/payments/{$orderNumber}", ['method' => 'qris'])->assertStatus(201);

        $res = $this->getJson('/api/admin/payments');
        $res->assertStatus(200)->assertJson(['success' => true]);
        $res->assertJsonStructure(['success', 'data', 'meta', 'summary' => ['paid_total', 'paid_count']]);

        $filtered = $this->getJson('/api/admin/payments?status=pending');
        $filtered->assertStatus(200);
        foreach ($filtered->json('data') as $row) {
            $this->assertEquals('pending', $row['status']);
        }
    }
}
