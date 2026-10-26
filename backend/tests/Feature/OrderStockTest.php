<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
    }

    public function test_order_decrements_stock(): void
    {
        $menuItem = MenuItem::first();
        $before = $menuItem->stock;

        $res = $this->postJson('/api/orders', [
            'table_code' => 'A12',
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 2]],
        ]);

        $res->assertStatus(201);
        $this->assertEquals($before - 2, $menuItem->fresh()->stock);
    }

    public function test_order_rejected_when_stock_insufficient(): void
    {
        $menuItem = MenuItem::first();
        $menuItem->update(['stock' => 1]);

        $res = $this->postJson('/api/orders', [
            'table_code' => 'A12',
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 5]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('Stok', $res->json('message'));
        // Stok tidak berubah karena transaksi di-rollback.
        $this->assertEquals(1, $menuItem->fresh()->stock);
    }

    public function test_order_rejected_when_menu_inactive(): void
    {
        $menuItem = MenuItem::first();
        $menuItem->update(['is_available' => false]);

        $res = $this->postJson('/api/orders', [
            'table_code' => 'A12',
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }
}
